<?php

namespace App\Models;

use App\Models\Concerns\HasFileVersions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Project extends Model
{
    use HasFactory, SoftDeletes, HasFileVersions;

    /**
     * How deep the project tree may go: a project, its sub-projects, and
     * theirs. `depth()` is 1-based, so the deepest allowed project is at 3.
     */
    public const MAX_DEPTH = 3;

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
        'budget_base',
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
        'budget_base' => 'decimal:2',
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

    // ── Walking the tree ──────────────────────────────────────────────────

    /**
     * Where this project sits in its tree, counting from 1: a top-level
     * project is 1, its sub-project 2, and a sub-sub-project 3.
     */
    public function depth(): int
    {
        $depth   = 1;
        $ancestor = $this->parent;

        while ($ancestor !== null) {
            $depth++;
            $ancestor = $ancestor->parent;
        }

        return $depth;
    }

    /** Whether the tree has room for another level under this project. */
    public function canHaveSubProjects(): bool
    {
        return $this->depth() < self::MAX_DEPTH;
    }

    /**
     * The top-level project this one hangs from — itself, when it is already
     * the root. A sub-project carries no request of its own, so ownership
     * questions resolve here.
     */
    public function rootAncestor(): self
    {
        $project = $this;

        while ($project->parent !== null) {
            $project = $project->parent;
        }

        return $project;
    }

    /** Every project below this one, at any depth. */
    public function descendants(): Collection
    {
        return $this->children->flatMap(
            fn (self $child) => collect([$child])->concat($child->descendants())
        );
    }

    /**
     * This project's id together with every descendant's — what a roll-up
     * across the whole subtree filters on.
     */
    public function subtreeIds(): array
    {
        return collect([$this->id])
            ->concat($this->descendants()->pluck('id'))
            ->all();
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

        // Each child contributes its own rolled-up figure, not its raw one, so
        // the tree folds one level at a time: a sub-sub-project's progress
        // reaches the root through its parent rather than being averaged in
        // flat beside it.
        $reported = $children->map(fn (self $child) => (float) $child->effectiveCompletionPercent())
            ->push((float) $this->completion_percent)
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

    /**
     * Re-derive the project cost: the contracted base plus every approved
     * variation order. Pending and rejected variations are not authorised
     * spend, so they leave the figure alone — the same rule billings use,
     * where only an approved statement counts as paid.
     *
     * Called whenever a variation is raised, edited, re-statused or removed,
     * and whenever the project form re-saves the base cost.
     */
    public function refreshBudgetTotal(): void
    {
        $approvedVariations = (float) $this->variationOrders()
            ->where('status', 'approved')
            ->sum('amount');

        $this->update(['budget_total' => (float) $this->budget_base + $approvedVariations]);
    }

    // ── Health / KPI ──────────────────────────────────────────────────────

    public function daysElapsed(): int
    {
        return $this->created_at?->diffInDays(now()) ?? 0;
    }

    public function daysRemaining(): int
    {
        return max(0, $this->daysUntilDeadline());
    }

    /**
     * Signed counterpart to daysRemaining(): negative once the deadline has
     * passed, so callers can tell "due today" apart from "already delayed".
     */
    public function daysUntilDeadline(): int
    {
        $deadline = $this->deadline ? Carbon::parse($this->deadline) : now();

        return now()->startOfDay()->diffInDays($deadline->startOfDay(), false);
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

    // ── Department ownership ──────────────────────────────────────────────

    /**
     * Narrow a project query to what a user is allowed to see. Internal roles
     * run the whole portfolio, so for them this is a no-op; a department user
     * sees only their own department's work.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null || $user->hasRole(User::INTERNAL_ROLES)) {
            return $query;
        }

        return $query->forDepartmentUser($user);
    }

    /**
     * Projects a department user owns: the ones they raised the request for,
     * plus the ones their department is down as owner of. Applied on its own
     * (rather than through `visibleTo`) where even an internal user should be
     * held to their own department's queue.
     *
     * A sub-project carries no request of its own, so the filter also reaches
     * up the tree — bounded by MAX_DEPTH, since each level nests another
     * `whereHas`.
     */
    public function scopeForDepartmentUser(Builder $query, ?User $user, int $levels = self::MAX_DEPTH): Builder
    {
        if ($user === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user, $levels) {
            $q->whereHas('projectRequest', fn (Builder $r) => $r->where('requester_id', $user->id));

            if (filled($user->department)) {
                $q->orWhere('dept_owner', $user->department);
            }

            if ($levels > 1) {
                $q->orWhereHas('parent', fn (Builder $p) => $p->forDepartmentUser($user, $levels - 1));
            }
        });
    }

    /**
     * Whether the project belongs to the user's department. Sub-projects
     * inherit the owner from their parent, so a blank one falls back to the
     * root's.
     */
    public function belongsToDepartmentOf(User $user): bool
    {
        if (blank($user->department)) {
            return false;
        }

        $owner = $this->dept_owner ?: $this->rootAncestor()->dept_owner;

        // Case-insensitive, to match how the SQL Server comparison behaves in
        // `scopeForDepartmentUser`.
        return filled($owner) && strcasecmp((string) $owner, (string) $user->department) === 0;
    }

    /**
     * The ids of everyone in the department that owns this project — who to
     * tell when there is no single requester waiting on it.
     */
    public function departmentAudience(): Collection
    {
        $owner = $this->dept_owner ?: $this->rootAncestor()->dept_owner;

        if (blank($owner)) {
            return collect();
        }

        return User::where('department', $owner)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /**
     * The engineers behind this project. The manager and the creator are
     * usually the same person, so they are de-duplicated and hear about a
     * thing once.
     */
    public function team(): Collection
    {
        return collect([$this->manager, $this->creator])
            ->filter()
            ->unique('id')
            ->values();
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
