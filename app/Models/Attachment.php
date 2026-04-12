<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use SoftDeletes;

    // Note: renamed from 'Attachments' to 'Attachment' (Laravel convention)
    protected $table = 'attachments';

    protected $fillable = [
        'filename',
        'filepath',
        'reference_id',
        'reference_type',
        'description',
    ];

    protected $appends = ['url'];

    // ── Relationships ──────────────────────────────────────────────────────

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    public function getUrlAttribute(): string
    {
        return Storage::url($this->filepath);
    }
}