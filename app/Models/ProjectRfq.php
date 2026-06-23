<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectRfq extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'contractor_name',
        'sent_date',
        'due_date',
        'status',
        'scope_of_work',
        'terms_conditions',
        'inclusions',
        'exclusions',
        'duration_days',
        'created_by',
        'quotation_file',
        'recipient_email',
    ];

    protected $casts = [
        'sent_date' => 'date',
        'due_date'  => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectRfqItem::class)->orderBy('seq');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
