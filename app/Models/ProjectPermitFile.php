<?php

namespace App\Models;

use App\Models\Concerns\HasFileVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPermitFile extends Model
{
    use HasFileVersions;

    protected $fillable = [
        'project_permit_id',
        'filename',
        'path',
        'mime_type',
    ];

    public function permit(): BelongsTo
    {
        return $this->belongsTo(ProjectPermit::class, 'project_permit_id');
    }
}
