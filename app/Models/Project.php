<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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
        'project_type',
        'proposal_document',
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
        'created_by' => 'integer',
        'project_manager_id' => 'integer',
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

    // ── Hub relations ─────────────────────────────────────────────────────

    public function rfqs(): HasMany
    {
        return $this->hasMany(ProjectRfq::class)->latest();
    }

    public function ntps(): HasMany
    {
        return $this->hasMany(ProjectNtp::class)->latest();
    }

    public function permits(): HasMany
    {
        return $this->hasMany(ProjectPermit::class)->latest();
    }

    public function variationOrders(): HasMany
    {
        return $this->hasMany(ProjectVariationOrder::class)->latest();
    }

    public function qualityDocs(): HasMany
    {
        return $this->hasMany(ProjectQualityDoc::class)->latest();
    }

    public function mtrDocs(): HasMany
    {
        return $this->hasMany(ProjectMtrDoc::class)->latest();
    }

    public function billings(): HasMany
    {
        return $this->hasMany(ProjectBilling::class)->latest();
    }

    public function iocItems(): HasMany
    {
        return $this->hasMany(ProjectIocItem::class)->latest();
    }

    public function weeklyReports(): HasMany
    {
        return $this->hasMany(ProjectWeeklyReport::class)->latest('submitted_date');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('target_date');
    }

    // ── Health / KPI ──────────────────────────────────────────────────────

    public function daysElapsed(): int
    {
        return $this->created_at?->diffInDays(now()) ?? 0;
    }

    public function daysRemaining(): int
    {
        $deadline = $this->deadline ? Carbon::parse($this->deadline) : now();

        return max(0, now()->startOfDay()->diffInDays($deadline->startOfDay(), false));
    }

    /**
     * "On-Time" requires actual completion to be at least the admin-configured
     * `project_completion_kpi` percentage of the completion expected from time elapsed
     * (e.g. 50% of the timeline elapsed with an 80% KPI expects >= 40% actual completion).
     */
    public function health(): string
    {
        if ($this->completion_percent >= 100) {
            return 'Advanced';
        }

        $daysElapsed = $this->daysElapsed();
        $daysRemaining = $this->daysRemaining();
        $totalDays = $daysElapsed + $daysRemaining;

        if ($totalDays <= 0) {
            return $daysRemaining === 0 ? 'Delayed' : 'On-Time';
        }

        $expectedPercent = min(100, ($daysElapsed / $totalDays) * 100);
        if ($expectedPercent <= 0) {
            return 'On-Time';
        }

        $kpiThreshold = (float) Setting::get('project_completion_kpi', 80);
        $onTrackRatio = ($this->completion_percent / $expectedPercent) * 100;

        return $onTrackRatio < $kpiThreshold ? 'Delayed' : 'On-Time';
    }

    // ── Notifications ─────────────────────────────────────────────────────

    public function notifyRequester(string $message, ?string $link = null): void
    {
        $requesterId = $this->projectRequest?->requester_id;

        if ($requesterId && $requesterId !== auth()->id()) {
            Notification::notify($requesterId, $message, $link ?? route('projects.show', $this->id, absolute: false));
        }
    }
}
