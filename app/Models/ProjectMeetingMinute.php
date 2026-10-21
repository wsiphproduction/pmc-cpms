<?php

namespace App\Models;

use App\Models\Concerns\HasFileVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectMeetingMinute extends Model
{
    use SoftDeletes, HasFileVersions;

    protected $fillable = [
        'project_id',
        'meeting_date',
        'file_path',
        'filename',
        'created_by',
    ];

    protected $casts = [
        'meeting_date' => 'date',
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
