<?php

namespace App\Models;

use App\Models\Concerns\HasFileVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One file in one of the hub's document-register sections: the file, what it
 * is, and which section it was filed under.
 */
class ProjectDocument extends Model
{
    use SoftDeletes, HasFileVersions;

    /**
     * The hub sections backed by this register, slug => label. The slug is the
     * hub URL segment (projects/{id}/hub/{slug}) and the stored `section`.
     */
    public const SECTIONS = [
        // A. Project Initiation and Proposal
        'capex-opex'         => 'CAPEX & OPEX',
        'sow'                => 'Scope of Work (SOW)',
        'feasibility'        => 'Feasibility Study',
        'site-investigation' => 'Site Investigation / Survey Reports',
        // B. Engineering and Cost Planning
        'drawings'           => 'Engineering Drawings',
        'tech-specs'         => 'Technical Specifications',
        'boq'                => 'Bill of Quantities (BOQ)',
        'cost-estimate'      => 'Detailed Cost Estimate',
        'gantt'              => 'Project Schedule / Gantt Chart',
        // D. Project Execution and Monitoring
        'site-observation'   => 'Site Observation Report',
        // F. Project Completion and Turnover
        'inspection-log'     => 'Project Inspection and Walkthrough Log',
        'punch-list'         => 'Project Construction Punch List',
        'as-built'           => 'As-Built Drawings',
        'om-manuals'         => 'Operation and Maintenance Manuals',
        'warranties'         => 'Equipment Warranties and Certificates',
        'turnover'           => 'Final Turnover Documents',
        'completion-other'   => 'Other Applicable Completion Documents',
    ];

    protected $fillable = [
        'project_id',
        'section',
        'description',
        'file_path',
        'filename',
        'created_by',
    ];

    public static function label(string $section): string
    {
        return self::SECTIONS[$section] ?? $section;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
