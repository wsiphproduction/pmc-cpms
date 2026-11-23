<?php

namespace App\Models;

use App\Models\Concerns\HasApprovalChain;
use App\Support\ContractorEvaluationScoring;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A project-level Contractor Performance Evaluation for one period.
 *
 * The project engineer rates all eight categories (forms A–H) and submits it —
 * the form's "Evaluated by". The PMD Assistant Manager reviews it and the PMD
 * Department Manager approves it; either may return it to the engineer, which
 * restarts the sign-off on resubmission. Only approved evaluations count
 * toward the contractor's consolidated performance.
 */
class ContractorEvaluation extends Model
{
    use SoftDeletes, HasApprovalChain;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_FOR_APPROVAL = 'for_approval';
    public const STATUS_APPROVED = 'approved';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT        => 'Draft',
        self::STATUS_FOR_APPROVAL => 'For Approval',
        self::STATUS_APPROVED     => 'Approved',
    ];

    public const APPROVAL_CHAIN = [
        User::ROLE_PMD_ASST_MANAGER,
        User::ROLE_PMD_DEPT_MANAGER,
    ];

    /** What each signature means on the form, by the role giving it. */
    public const SIGNATURE_VERBS = [
        User::ROLE_PMD_ASST_MANAGER => 'reviewed',
        User::ROLE_PMD_DEPT_MANAGER => 'approved',
    ];

    protected $fillable = [
        'project_id',
        'control_no',
        'period_type',
        'period_label',
        'period_start',
        'period_end',
        'contractor_name',
        'supplier_id',
        'status',
        'total_score',
        'submitted_at',
        'approved_at',
        'return_remarks',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'total_score'  => 'float',
        'supplier_id'  => 'integer',
        'created_by'   => 'integer',
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ContractorEvaluationCategory::class);
    }

    /** The next control number, CPE-<year>-<seq>, counting deleted evaluations too. */
    public static function nextControlNo(): string
    {
        $prefix = 'CPE-' . now()->format('Y') . '-';
        $last = static::withTrashed()->where('control_no', 'like', $prefix . '%')->max('control_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * The contractor's Master Data record, matched on its company name — NTPs
     * carry the contractor as text, so the link is resolved here.
     */
    public static function supplierIdFor(string $contractorName): ?int
    {
        $id = Supplier::whereRaw('LOWER(TRIM(company)) = ?', [mb_strtolower(trim($contractorName))])->value('id');

        return $id ? (int) $id : null;
    }

    /** Still a draft: the engineer may rate, edit, submit or delete it. */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_FOR_APPROVAL && $role = $this->currentApprovalRole()) {
            return $role === User::ROLE_PMD_ASST_MANAGER ? 'For Review' : 'For Approval';
        }

        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Make sure all eight category rows exist, in form order. */
    public function ensureCategories(): void
    {
        $existing = $this->categories()->pluck('category')->all();

        foreach (array_keys(ContractorEvaluationScoring::categories()) as $category) {
            if (! in_array($category, $existing, true)) {
                $this->categories()->create(['category' => $category]);
            }
        }

        $this->unsetRelation('categories');
    }

    /** Recompute the project score from the category scores. */
    public function refreshTotal(): void
    {
        $scores = $this->categories()->pluck('score', 'category')->all();

        $this->update(['total_score' => ContractorEvaluationScoring::totalScore($scores)]);
    }
}
