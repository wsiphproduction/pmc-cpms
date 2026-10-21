<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPhoto extends Model
{
    protected $fillable = [
        'project_photo_album_id',
        'file_path',
        'filename',
        'created_by',
    ];

    public function album(): BelongsTo
    {
        return $this->belongsTo(ProjectPhotoAlbum::class, 'project_photo_album_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
