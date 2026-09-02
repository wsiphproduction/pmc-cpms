<?php

namespace App\Models;

use App\Models\Concerns\HasApprovalChain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectNtp extends Model
{
    use SoftDeletes, HasApprovalChain;

    /**
     * Sign-off order. The requesting department reviews first — they are the
     * ones the work is for — and PMD signs off after them. The NTP is only
     * issued (and its RFQ awarded) once the Division Manager has signed.
     */
    public const APPROVAL_CHAIN = [
        User::ROLE_REQUESTOR,
        User::ROLE_PMD_ASST_MANAGER,
        User::ROLE_PMD_DEPT_MANAGER,
        User::ROLE_DIVISION_MANAGER,
    ];

    protected $fillable = [
        'project_id',
        'ntp_no',
        'contractor_name',
        'project_rfq_id',
        'baseline_start',
        'baseline_end',
        'approved_cost',
        'status',
        'issued_date',
        'issued_by',
        'vendor_notified_at',
        'reviewed_by',
        'reviewed_at',
        'review_remarks',
        'created_by',
    ];

    protected $casts = [
        'baseline_start' => 'date',
        'baseline_end'   => 'date',
        'issued_date'    => 'date',
        'reviewed_at'    => 'datetime',
        'vendor_notified_at' => 'datetime',
        'approved_cost'  => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(ProjectRfq::class, 'project_rfq_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The department step belongs to *this project's* department user, not to
     * every department user in the company. The rest of the chain is held by a
     * single office each, so holding the role is enough.
     */
    public function approvalStepAuthorizes(ApprovalStep $step, User $user): bool
    {
        if ($step->role !== User::ROLE_REQUESTOR) {
            return $user->hasRole($step->role);
        }

        $project = $this->project;

        if ($project === null) {
            return false;
        }

        // The person who raised the request signs for it, whatever else they
        // hold. Otherwise the step falls to the owning department's users —
        // and only its department users: matching on department name alone let
        // any account carrying that department value sign for it.
        return $project->projectRequest?->requester_id === $user->id
            || ($user->hasRole(User::ROLE_REQUESTOR) && $project->belongsToDepartmentOf($user));
    }
}
