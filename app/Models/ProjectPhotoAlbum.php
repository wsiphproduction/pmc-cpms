<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The photos taken on a project on one date. Uploading again on that date adds to it. */
class ProjectPhotoAlbum extends Model
{
    protected $fillable = [
        'project_id',
        'photo_date',
        'created_by',
    ];

    protected $casts = [
        'photo_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProjectPhoto::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
