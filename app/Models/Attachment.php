<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use SoftDeletes, HasFactory;

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
        return asset('storage/' . $this->filepath);
    }
}