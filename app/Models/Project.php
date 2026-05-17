<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_no',
        'project_request_id',
        'title',
        'project_manager_id',
        'project_manager_name',
        'site',
        'asset_id',
        'class_name',
        'priority',
        'status_key',
        'work_force',
        'wr_no',
        'wr_date',
        'dept_owner',
        'cost_code',
        'category',
        'service_type',
        'deadline',
        'owner_email',
        'structure_type',
        'jip',
        'need_civil',
        'need_electrical',
        'need_mechanical',
        'notes',
        'budget_total',
        'budget_paid',
        'completion_percent',
        'created_by',
    ];

    protected $casts = [
        'wr_date' => 'date',
        'deadline' => 'date',
        'jip' => 'boolean',
        'need_civil' => 'boolean',
        'need_electrical' => 'boolean',
        'need_mechanical' => 'boolean',
        'budget_total' => 'decimal:2',
        'budget_paid' => 'decimal:2',
        'completion_percent' => 'integer',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function projectRequest(): BelongsTo
    {
        return $this->belongsTo(ProjectRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ProjectStatusLog::class)->latest();
    }
}
