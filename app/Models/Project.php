<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    /** Human-readable label for each status_key. */
    public const STATUS_LABELS = [
        'PLANNING' => 'For Planning',
        'RFQ_SUBMITTED' => 'RFQ/RFP Submitted',
        'PROPOSAL_REVIEW' => 'Proposal Under Review',
        'DESIGN_REVIEW' => 'Detailed Design Under Review',
        'EXEC_ENDORSED' => 'Endorsed for Executive Approval',
        'NTP_PROCESSING' => 'NTP & Contract Processing',
        'SCHEDULING' => 'For Scheduling',
        'ONGOING' => 'Ongoing',
        'ON_HOLD' => 'On Hold',
        'COMPLETED' => 'Completed',
        'CLOSED' => 'Closed',
        'CANCELED' => 'Canceled',
    ];

    protected $fillable = [
        'project_no',
        'parent_id',
        'source_ntp_id',
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
        'parent_id' => 'integer',
        'source_ntp_id' => 'integer',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    // ── Sub-project relations ─────────────────────────────────────────────

    /** The main project this sub-project belongs to (null for main projects). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'parent_id');
    }

    /** Sub-projects spawned from this project's issued NTPs. */
    public function children(): HasMany
    {
        return $this->hasMany(Project::class, 'parent_id')->latest();
    }

    /** The issued NTP a sub-project was created from (null for main projects). */
    public function sourceNtp(): BelongsTo
    {
        return $this->belongsTo(ProjectNtp::class, 'source_ntp_id');
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

    public function completion(): HasOne
    {
        return $this->hasOne(ProjectCompletion::class);
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

    // ── Sub-project roll-ups ──────────────────────────────────────────────
    //
    // A project always reports its own physical completion (its own weekly
    // reports). When it also has sub-projects, its headline completion blends
    // the project's own value with each sub-project's, equally weighted — but
    // entries still at 0% (not yet started/reported) are skipped so a freshly
    // created sub-project doesn't drag the number down (own 40% + sub 0% → 40%;
    // own 40% + subs 30% & 60% → 43%). Financials stay independent.

    /** Children used for roll-ups; prefers the eager-loaded relation to avoid N+1. */
    private function rollupChildren()
    {
        return $this->relationLoaded('children') ? $this->children : $this->children()->get();
    }

    public function hasSubProjects(): bool
    {
        return $this->rollupChildren()->isNotEmpty();
    }

    /**
     * Physical completion. With no sub-projects it's the project's own value;
     * with sub-projects it's the equal-weighted average of the project's own
     * value and each sub-project's, ignoring entries that are still at 0%.
     * When every entry is 0%, the result is 0%.
     */
    public function effectiveCompletionPercent(): int
    {
        $children = $this->rollupChildren();

        if ($children->isEmpty()) {
            return (int) $this->completion_percent;
        }

        $reported = $children->pluck('completion_percent')
            ->push($this->completion_percent)
            ->map(fn ($v) => (float) $v)
            ->filter(fn ($v) => $v > 0);

        return $reported->isEmpty() ? 0 : (int) round($reported->avg());
    }

    /**
     * Re-derive the project's own completion from its latest weekly report.
     * Called whenever a report is added or removed, from either the operations
     * hub or the Weekly Status module.
     */
    public function refreshCompletionFromReports(): void
    {
        $this->update(['completion_percent' => $this->weeklyReports()->first()?->completion_pct ?? 0]);
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
        $completion = $this->effectiveCompletionPercent();

        if ($completion >= 100) {
            // Finished on or before the deadline is "Ahead"; finished after
            // (or with no deadline to beat) is simply "Completed".
            if ($this->deadline && now()->startOfDay()->lte(Carbon::parse($this->deadline)->startOfDay())) {
                return 'Ahead';
            }

            return 'Completed';
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
        $onTrackRatio = ($completion / $expectedPercent) * 100;

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
