<?php

namespace App\Models;

use App\Models\Concerns\HasFileVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectWeeklyReport extends Model
{
    use SoftDeletes, HasFileVersions;

    protected $fillable = [
        'project_id',
        'project_ntp_id',
        'week_code',
        'completion_pct',
        'identified_issues',
        'progress_updates',
        'checklist',
        'issues',
        'file_path',
        'filename',
        'submitted_date',
        'created_by',
    ];

    protected $casts = [
        'submitted_date' => 'date',
        'completion_pct' => 'integer',
        // SQL Server hands integer columns back as strings; the front end
        // compares these against project / NTP ids numerically.
        'project_id'     => 'integer',
        'project_ntp_id' => 'integer',
        'created_by'     => 'integer',
        'checklist'      => 'array',
        'issues'         => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function ntp(): BelongsTo
    {
        return $this->belongsTo(ProjectNtp::class, 'project_ntp_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
