<?php

namespace App\Models;

use App\Models\Concerns\HasFileVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use SoftDeletes, HasFactory, HasFileVersions;

    // Note: renamed from 'Attachments' to 'Attachment' (Laravel convention)
    protected $table = 'attachments';

    protected $fillable = [
        'filename',
        'filepath',
        'type',
        'reference_id',
        'reference_type',
        'description',
    ];

    protected $appends = ['url', 'versions'];

    /** The raw relation is replaced by the `versions` payload below. */
    protected $hidden = ['file_versions'];

    // ── Relationships ──────────────────────────────────────────────────────

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->filepath);
    }

    /** Upload history for this attachment, newest first. */
    public function getVersionsAttribute(): array
    {
        return $this->fileVersionPayload();
    }
}