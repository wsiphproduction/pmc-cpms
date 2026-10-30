<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\EmsSurveyReport;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What the EMS Survey Report forms send: the header, the assessment and the
 * supporting documents. The engineer's pop-up on the project hub and the
 * representative's MEPEO page both fill in the assessment, so they validate
 * it the same way here.
 */
class EmsReportInput
{
    public const MAX_FILE_KB = 20480;

    public const FILE_TYPES = 'pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,ppt,pptx';

    /** The project-side header above the assessment. */
    public static function header(Request $request): array
    {
        return $request->validate([
            'date_requested' => ['required', 'date'],
            'location'       => ['nullable', 'string', 'max:255'],
            'contractor'     => ['nullable', 'string', 'max:255'],
            'target_start'   => ['nullable', 'date'],
            'target_end'     => ['nullable', 'date', 'after_or_equal:target_start'],
        ]);
    }

    /**
     * The checklists, write-ups and MEPEO's finding. Only submitting to the
     * MEPEO Manager asks for the Status and at least one ticked activity and
     * aspect (or an "Others"); until then any of it may stay blank.
     */
    public static function body(Request $request, bool $submitting = false): array
    {
        $data = $request->validate([
            'major_activities'       => ['nullable', 'array'],
            'major_activities.*'     => ['string', Rule::in(array_keys(config('ems.major_activities')))],
            'major_activities_other' => ['nullable', 'string', 'max:255'],
            'other_scope'            => ['nullable', 'string', 'max:5000'],
            'aspects'                => ['nullable', 'array'],
            'aspects.*'              => ['string', Rule::in(array_keys(config('ems.aspects')))],
            'aspects_other'          => ['nullable', 'string', 'max:255'],
            'impacts'                => ['nullable', 'string', 'max:5000'],
            'mitigation'             => ['nullable', 'string', 'max:5000'],
            'mepeo_status'           => [$submitting ? 'required' : 'nullable', Rule::in(array_keys(config('ems.statuses')))],
            'remarks'                => ['nullable', 'string', 'max:5000'],
        ], [
            'mepeo_status.required' => 'Choose the Status before submitting.',
        ]);

        // A form posted as multipart drops an empty list entirely.
        $data['major_activities'] = array_values(array_unique($data['major_activities'] ?? []));
        $data['aspects'] = array_values(array_unique($data['aspects'] ?? []));

        if ($submitting) {
            $errors = [];

            if ($data['major_activities'] === [] && blank($data['major_activities_other'] ?? null)) {
                $errors['major_activities'] = 'Tick at least one major activity, or fill in Others.';
            }

            if ($data['aspects'] === [] && blank($data['aspects_other'] ?? null)) {
                $errors['aspects'] = 'Tick at least one environmental aspect, or fill in Others.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        return $data;
    }

    /**
     * Supporting documents to add (`attachments[]`) and to remove
     * (`removed_attachments[]`, by id). Validated before anything is saved,
     * so a rejected file leaves the report as it was.
     *
     * @return array{files: UploadedFile[], removed: int[]}
     */
    public static function attachments(Request $request): array
    {
        $request->validate([
            'attachments'           => ['nullable', 'array', 'max:20'],
            'attachments.*'         => ['file', 'max:' . self::MAX_FILE_KB, 'mimes:' . self::FILE_TYPES],
            'removed_attachments'   => ['nullable', 'array'],
            'removed_attachments.*' => ['integer'],
        ], [
            'attachments.*.max'   => 'Each supporting document must be 20 MB or smaller.',
            'attachments.*.mimes' => 'Supporting documents must be PDF, image, Word, Excel or PowerPoint files.',
        ]);

        return [
            'files'   => array_values($request->file('attachments') ?? []),
            'removed' => array_map('intval', $request->input('removed_attachments', [])),
        ];
    }

    /** Apply what attachments() validated. Removing one deletes its file too. */
    public static function syncAttachments(EmsSurveyReport $report, array $changes): void
    {
        if ($changes['removed'] !== []) {
            $report->attachments()->whereKey($changes['removed'])->get()
                ->each(fn (Attachment $attachment) => self::purge($attachment));
        }

        foreach ($changes['files'] as $file) {
            $path = $file->store("ems/{$report->id}", 'public');

            $attachment = $report->attachments()->create([
                'filename' => $file->getClientOriginalName(),
                'filepath' => $path,
                'type'     => 'other',
            ]);

            $attachment->recordFileVersion(
                $path,
                $file->getClientOriginalName(),
                mimeType: $file->getMimeType(),
                size: $file->getSize(),
            );
        }
    }

    public static function purge(Attachment $attachment): void
    {
        $attachment->purgeFileVersions();
        Storage::disk('public')->delete($attachment->filepath);
        $attachment->delete();
    }
}
