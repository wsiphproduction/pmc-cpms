<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectBilling;
use App\Models\ProjectIocItem;
use App\Models\ProjectMtrDoc;
use App\Models\ProjectPermitFile;
use App\Models\ProjectQualityDoc;
use App\Models\ProjectVariationOrder;
use App\Models\ProjectWeeklyReport;
use App\Models\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Replacing the file behind any project record, in one place.
 *
 * Each hub module holds its file in its own column, so rather than eight
 * near-identical endpoints the slot is named in the URL and looked up here.
 * The list is a fixed whitelist — a slug that is not in it is a 404, so no
 * request can reach a model this was not meant to touch.
 *
 * A replacement never deletes what it replaces: the old file stays on disk and
 * stays reachable through the record's version history.
 */
class FileVersionController extends Controller
{
    /**
     * slug => [model, path column, name column (null = derive), storage folder].
     * The folder is a callable so it can be scoped per project, matching where
     * each module already writes its uploads.
     */
    private const SLOTS = [
        'permit-file' => [ProjectPermitFile::class,     'path',              'filename', 'hub/permits/%d'],
        'vof'         => [ProjectVariationOrder::class, 'attachment',        null,       'vof-files'],
        'qpp'         => [ProjectQualityDoc::class,     'file_path',         'filename', 'hub/qpp/%d'],
        'mtr'         => [ProjectMtrDoc::class,         'file_path',         'filename', 'hub/mtr/%d'],
        'rfp'         => [ProjectBilling::class,        'file_path',         'filename', 'hub/rfp/%d'],
        'ioc'         => [ProjectIocItem::class,        'file_path',         'filename', 'hub/ioc/%d'],
        'psr'         => [ProjectWeeklyReport::class,   'file_path',         'filename', 'hub/psr/%d'],
        'proposal'    => [Project::class,               'proposal_document', null,       'proposals'],
    ];

    /** Which hub module each slot belongs to, for the audit trail. */
    private const MODULES = [
        'permit-file' => 'Permit',
        'vof'         => 'VOF',
        'qpp'         => 'QPP',
        'mtr'         => 'MTR',
        'rfp'         => 'RFP',
        'ioc'         => 'IOC',
        'psr'         => 'PSR',
        'proposal'    => 'Project',
    ];

    public function replace(Request $request, Project $project, string $slot, int $id): RedirectResponse
    {
        $this->authorize('update', $project);

        abort_unless(isset(self::SLOTS[$slot]), 404);

        [$class, $pathColumn, $nameColumn, $folder] = self::SLOTS[$slot];

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Model $record */
        $record = $class::findOrFail($id);

        abort_unless($this->belongsToProject($record, $project), 403);

        $file      = $request->file('file');
        $directory = str_contains($folder, '%d') ? sprintf($folder, $project->id) : $folder;
        $version   = $record->storeVersionedFile($file, $directory, note: $request->input('note'));

        $columns = [$pathColumn => $version->filepath];
        if ($nameColumn) {
            $columns[$nameColumn] = $version->filename;
        }
        $record->update($columns);

        AuditTrail::log(
            "File replaced ({$version->label}): {$version->filename}",
            $project,
            ['module' => self::MODULES[$slot], 'type' => 'upload'],
        );

        return back()->with('success', "File updated to {$version->label}.");
    }

    /**
     * A record is only replaceable through the project that owns it — a permit
     * file reaches its project through its permit, and a project's proposal is
     * the project itself.
     */
    private function belongsToProject(Model $record, Project $project): bool
    {
        if ($record instanceof Project) {
            return (int) $record->id === (int) $project->id;
        }

        if ($record instanceof ProjectPermitFile) {
            return (int) $record->permit?->project_id === (int) $project->id;
        }

        return (int) $record->project_id === (int) $project->id;
    }
}
