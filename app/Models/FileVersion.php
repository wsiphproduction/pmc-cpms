<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One upload of one file slot. The newest row is what the owning record points
 * at; the rows below it are the superseded files, kept on disk so history stays
 * openable.
 */
class FileVersion extends Model
{
    protected $fillable = [
        'versionable_type',
        'versionable_id',
        'collection',
        'version',
        'filename',
        'filepath',
        'mime_type',
        'size',
        'note',
        'uploaded_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'size'    => 'integer',
    ];

    protected $appends = ['url', 'label'];

    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->filepath);
    }

    /** "v1", "v2", … — what the UI shows next to the file name. */
    public function getLabelAttribute(): string
    {
        return 'v' . $this->version;
    }
}
