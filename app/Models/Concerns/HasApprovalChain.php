<?php

namespace App\Models\Concerns;

use App\Models\ApprovalStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A fixed, sequential approval chain for a record.
 *
 * The implementing model declares `const APPROVAL_CHAIN = [role, role, …]` in
 * signing order. Every step is written up front so the whole chain can be shown
 * before it completes; steps settle strictly in order, and the first pending one
 * is the only step anybody can act on.
 */
trait HasApprovalChain
{
    public function approvals(): MorphMany
    {
        return $this->morphMany(ApprovalStep::class, 'approvable')->orderBy('sequence');
    }

    /** Write out the full chain. Safe to call once, when the record is created. */
    public function startApprovalChain(): void
    {
        if ($this->approvals()->exists()) {
            return;
        }

        foreach (array_values(static::APPROVAL_CHAIN) as $index => $role) {
            $this->approvals()->create([
                'role'     => $role,
                'sequence' => $index + 1,
                'status'   => 'pending',
            ]);
        }

        $this->unsetRelation('approvals');
    }

    /** The step now awaiting a decision, or null once the chain is settled. */
    public function currentApproval(): ?ApprovalStep
    {
        if ($this->approvalRejected()) {
            return null;
        }

        return $this->approvalSteps()->firstWhere('status', 'pending');
    }

    public function currentApprovalRole(): ?string
    {
        return $this->currentApproval()?->role;
    }

    public function approvalChainStarted(): bool
    {
        return $this->approvalSteps()->isNotEmpty();
    }

    public function approvalChainComplete(): bool
    {
        return $this->approvalChainStarted()
            && $this->approvalSteps()->every(fn (ApprovalStep $step) => $step->status === 'approved');
    }

    public function approvalRejected(): bool
    {
        return $this->approvalSteps()->contains(fn (ApprovalStep $step) => $step->status === 'rejected');
    }

    /** True when this user is the one the chain is currently waiting on. */
    public function awaitingApprovalFrom(User $user): bool
    {
        $step = $this->currentApproval();

        return $step !== null && $this->approvalStepAuthorizes($step, $user);
    }

    /**
     * Whether a user may settle a given step. Defaults to simply holding the
     * step's role; models override this where the holder is narrower than the
     * role (an NTP's first step belongs to *that project's* department user).
     */
    public function approvalStepAuthorizes(ApprovalStep $step, User $user): bool
    {
        return $user->hasRole($step->role);
    }

    /** Settle the current step. Returns the step, or null if there was nothing to act on. */
    public function recordApproval(User $user, ?string $remarks = null): ?ApprovalStep
    {
        return $this->settleCurrentStep($user, 'approved', $remarks);
    }

    public function recordRejection(User $user, ?string $remarks = null): ?ApprovalStep
    {
        return $this->settleCurrentStep($user, 'rejected', $remarks);
    }

    /**
     * Records whose first pending step belongs to the given role — i.e. the ones
     * actually sitting in that role's queue, not merely listing it somewhere in
     * their chain.
     */
    public function scopeAwaitingRole(Builder $query, string $role): Builder
    {
        $sequence = array_search($role, array_values(static::APPROVAL_CHAIN), true);

        // A role outside this chain is never waiting on anything.
        if ($sequence === false) {
            return $query->whereRaw('1 = 0');
        }

        $sequence++;

        return $query
            ->whereHas('approvals', fn (Builder $q) => $q
                ->where('sequence', $sequence)
                ->where('status', 'pending'))
            // Every earlier step must already be signed off, so a chain stalled
            // (or rejected) upstream stays out of this role's queue.
            ->whereDoesntHave('approvals', fn (Builder $q) => $q
                ->where('sequence', '<', $sequence)
                ->where('status', '!=', 'approved'));
    }

    /** Any chain still open — nothing rejected and at least one step pending. */
    public function scopeApprovalPending(Builder $query): Builder
    {
        return $query
            ->whereHas('approvals', fn (Builder $q) => $q->where('status', 'pending'))
            ->whereDoesntHave('approvals', fn (Builder $q) => $q->where('status', 'rejected'));
    }

    /** Chain state for the UI, in signing order. */
    public function approvalTimeline(): array
    {
        $current = $this->currentApproval();

        return $this->approvalSteps()->map(fn (ApprovalStep $step) => [
            'sequence'   => $step->sequence,
            'role'       => $step->role,
            'role_label' => User::roleLabel($step->role),
            'status'     => $step->status,
            'is_current' => $current !== null && $current->id === $step->id,
            'actor'      => $step->user?->name,
            'acted_at'   => $step->acted_at?->format('M d, Y h:i A'),
            'remarks'    => $step->remarks,
        ])->values()->all();
    }

    /** Prefers the eager-loaded relation so lists don't fire a query per row. */
    private function approvalSteps()
    {
        return $this->relationLoaded('approvals') ? $this->approvals : $this->approvals()->get();
    }

    private function settleCurrentStep(User $user, string $status, ?string $remarks): ?ApprovalStep
    {
        $step = $this->currentApproval();

        if ($step === null) {
            return null;
        }

        $step->update([
            'status'   => $status,
            'user_id'  => $user->id,
            'acted_at' => now(),
            'remarks'  => $remarks,
        ]);

        $this->unsetRelation('approvals');

        return $step;
    }
}
