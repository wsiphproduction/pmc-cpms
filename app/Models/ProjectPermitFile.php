<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPermitFile extends Model
{
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
