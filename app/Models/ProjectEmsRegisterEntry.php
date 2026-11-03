<?php

namespace App\Models;

use App\Models\Concerns\HasFileVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One file in the project's EMS Register: the file and what it is. */
class ProjectEmsRegisterEntry extends Model
{
    use SoftDeletes, HasFileVersions;

    protected $fillable = [
        'project_id',
        'description',
        'file_path',
        'filename',
        'created_by',
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
