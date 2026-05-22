<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectWeeklyReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'week_code',
        'completion_pct',
        'identified_issues',
        'progress_updates',
        'file_path',
        'filename',
        'submitted_date',
        'created_by',
    ];

    protected $casts = [
        'submitted_date' => 'date',
        'completion_pct' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
