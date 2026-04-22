<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectRequest extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'title',
        'job_type',
        'description',
        'requester_id',
        'job_location',
        'costcode',
        'opex',
        'capex',
        'for_budgeting',
        'status',
    ];

    protected $casts = [
        'opex'         => 'boolean',
        'capex'        => 'boolean',
        'for_budgeting'=> 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'reference', 'reference_type', 'reference_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'reference', 'reference_type', 'reference_id');
    }

    public function auditTrails(): MorphMany
    {
        return $this->morphMany(AuditTrail::class, 'reference', 'reference_type', 'reference_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }
}