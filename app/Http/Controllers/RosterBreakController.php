<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\RosterBreak;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roster breaks for the PMD managers.
 *
 * A PMD Department Manager or PMD Assistant Manager schedules the dates they
 * will be away and names an officer-in-charge. For the length of the break that
 * OIC acts as the manager's role in the approval chains — requests and NTPs
 * waiting on that role land in the OIC's queue, and whatever the OIC settles
 * is recorded as signed on the manager's behalf. Nothing is taken from the
 * manager: they can still sign themselves while away.
 *
 * The OIC has to be free for the whole break — not away on a break of their
 * own — and a manager who is covering somebody cannot leave during that cover.
 */
class RosterBreakController extends Controller
{
    /**
     * The roles that take roster breaks, each with the roles that may cover it.
     * The Department Manager is covered by an Assistant Manager; an Assistant
     * Manager by a fellow Assistant Manager or by the Department Manager.
     */
    private const OIC_ROLES = [
        User::ROLE_PMD_DEPT_MANAGER => [User::ROLE_PMD_ASST_MANAGER],
        User::ROLE_PMD_ASST_MANAGER => [User::ROLE_PMD_ASST_MANAGER, User::ROLE_PMD_DEPT_MANAGER],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $oicRoles = self::OIC_ROLES[$this->coveredRole($user)];

        $candidates = User::whereHas('roles', fn ($q) => $q->whereIn('name', $oicRoles))
            ->where('id', '!=', $user->id)
            ->with('roles')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Their own current and upcoming breaks, so the form can grey out
        // anyone who is away on the chosen dates before the server refuses it.
        $away = RosterBreak::whereIn('user_id', $candidates->pluck('id'))
            ->whereDate('ends_on', '>=', today()->toDateString())
            ->orderBy('starts_on')
            ->get()
            ->groupBy('user_id');

        return Inertia::render('roster-break/index', [
            'breaks' => $user->rosterBreaks()
                ->with('oic')
                ->orderByDesc('starts_on')
                ->get()
                ->map(fn (RosterBreak $break) => $this->row($break))
                ->values()->all(),
            'oic_options' => $candidates
                ->map(fn (User $u) => [
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'email'      => $u->email,
                    'role_label' => User::roleLabel($this->firstHeld($u, $oicRoles)),
                    'away'       => ($away[$u->id] ?? collect())
                        ->map(fn (RosterBreak $b) => [
                            'starts_on' => $b->starts_on->toDateString(),
                            'ends_on'   => $b->ends_on->toDateString(),
                            'span'      => $this->span($b),
                        ])->values()->all(),
                ])
                ->values()->all(),
            'oic_who' => $this->oicWho($oicRoles),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request, $user);

        $break = $user->rosterBreaks()->create($data + ['role' => $this->coveredRole($user)]);
        $break->load('oic');

        Notification::notify(
            $break->oic_user_id,
            "{$user->name} has named you OIC ({$this->coveredLabel($break)}) for their roster break, "
                . $this->span($break) . '. Their approvals will sit in your For Approval queue for that period.'
                . $this->notesSuffix($break),
            route('approvals.index', absolute: false)
        );

        return back()->with('success', "Roster break scheduled — {$break->oic?->name} will cover for you " . $this->span($break) . '.');
    }

    public function update(Request $request, RosterBreak $rosterBreak): RedirectResponse
    {
        $user = $request->user();
        $this->own($rosterBreak, $user);

        abort_if($rosterBreak->isOver(), 422, 'A break that has already ended cannot be changed.');

        $previousOic = $rosterBreak->oic_user_id;
        $rosterBreak->update($this->validated($request, $user, $rosterBreak));
        $rosterBreak->load('oic');

        if ($previousOic !== $rosterBreak->oic_user_id) {
            Notification::notify(
                $previousOic,
                "{$user->name}'s roster break " . $this->span($rosterBreak) . ' is now covered by somebody else; you are no longer its OIC.',
                route('approvals.index', absolute: false)
            );
        }

        Notification::notify(
            $rosterBreak->oic_user_id,
            "{$user->name}'s roster break has been updated: you are OIC ({$this->coveredLabel($rosterBreak)}) " . $this->span($rosterBreak) . '.'
                . $this->notesSuffix($rosterBreak),
            route('approvals.index', absolute: false)
        );

        return back()->with('success', 'Roster break updated.');
    }

    public function destroy(Request $request, RosterBreak $rosterBreak): RedirectResponse
    {
        $user = $request->user();
        $this->own($rosterBreak, $user);

        // Past breaks are the record of who covered when; they stay.
        abort_if($rosterBreak->isOver(), 422, 'A break that has already ended cannot be cancelled.');

        $rosterBreak->delete();

        Notification::notify(
            $rosterBreak->oic_user_id,
            "{$user->name} has cancelled their roster break " . $this->span($rosterBreak) . '; you are no longer its OIC.',
            route('approvals.index', absolute: false)
        );

        return back()->with('success', 'Roster break cancelled.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * @return array{oic_user_id: int, starts_on: string, ends_on: string, notes: ?string}
     */
    private function validated(Request $request, User $user, ?RosterBreak $except = null): array
    {
        $data = $request->validate([
            'oic_user_id' => [
                'required', 'integer',
                Rule::notIn([$user->id]),
                // Must be a live account; the role is checked below.
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on'   => ['required', 'date', 'after_or_equal:starts_on'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ], [
            'oic_user_id.not_in' => 'You cannot name yourself as OIC.',
            'ends_on.after_or_equal' => 'The break must end on or after the day it starts.',
        ]);

        $oicRoles = self::OIC_ROLES[$except?->role ?? $this->coveredRole($user)];
        $oic = User::find($data['oic_user_id']);

        if (! $oic?->hasRole($oicRoles)) {
            throw ValidationException::withMessages([
                'oic_user_id' => 'The OIC must be ' . $this->oicWho($oicRoles) . '.',
            ]);
        }

        // A break entirely in the past would never cover anything. An active
        // one may keep its original start date when edited, so only the end
        // is held to today.
        if (Carbon::parse($data['ends_on'])->lt(today())) {
            throw ValidationException::withMessages([
                'ends_on' => 'The break has to end today or later.',
            ]);
        }

        if ($except === null && Carbon::parse($data['starts_on'])->lt(today())) {
            throw ValidationException::withMessages([
                'starts_on' => 'The break cannot start in the past.',
            ]);
        }

        $clash = $user->rosterBreaks()
            ->overlapping($data['starts_on'], $data['ends_on'])
            ->when($except, fn ($q) => $q->where('id', '!=', $except->id))
            ->first();

        if ($clash) {
            throw ValidationException::withMessages([
                'starts_on' => 'These dates overlap another roster break (' . $this->span($clash) . ').',
            ]);
        }

        // The OIC must be around to cover: not away on a break of their own.
        $oicAway = $oic->rosterBreaks()->overlapping($data['starts_on'], $data['ends_on'])->first();

        if ($oicAway) {
            throw ValidationException::withMessages([
                'oic_user_id' => "{$oic->name} is on their own roster break " . $this->span($oicAway) . '. Pick someone who is free on these dates.',
            ]);
        }

        // Nor can the manager leave while they are covering somebody else.
        $covering = $user->oicDuties()
            ->overlapping($data['starts_on'], $data['ends_on'])
            ->with('user')
            ->first();

        if ($covering) {
            throw ValidationException::withMessages([
                'starts_on' => 'You are OIC for ' . ($covering->user?->name ?? 'another manager') . "'s roster break "
                    . $this->span($covering) . ' and cannot be away on those dates.',
            ]);
        }

        return [
            'oic_user_id' => (int) $data['oic_user_id'],
            'starts_on'   => Carbon::parse($data['starts_on'])->toDateString(),
            'ends_on'     => Carbon::parse($data['ends_on'])->toDateString(),
            'notes'       => $data['notes'] ?? null,
        ];
    }

    private function own(RosterBreak $break, User $user): void
    {
        abort_unless($break->user_id === $user->id, 403);
    }

    private function row(RosterBreak $break): array
    {
        return [
            'id'        => $break->id,
            'oic'       => $break->oic ? ['id' => $break->oic->id, 'name' => $break->oic->name] : null,
            'starts_on' => $break->starts_on->toDateString(),
            'ends_on'   => $break->ends_on->toDateString(),
            'span'      => $this->span($break),
            'days'      => (int) $break->starts_on->diffInDays($break->ends_on) + 1,
            'notes'     => $break->notes,
            'state'     => $break->state(),
        ];
    }

    private function span(RosterBreak $break): string
    {
        return $break->starts_on->format('M d, Y') . ' – ' . $break->ends_on->format('M d, Y');
    }

    /** The manager's notes, tacked onto the OIC's notification when there are any. */
    private function notesSuffix(RosterBreak $break): string
    {
        return $break->notes ? " Notes: {$break->notes}" : '';
    }

    private function coveredLabel(RosterBreak $break): string
    {
        return User::roleLabel($break->role);
    }

    /** The role this user's breaks cover — the Department Manager seat first, as the narrower one. */
    private function coveredRole(User $user): string
    {
        $role = $this->firstHeld($user, array_keys(self::OIC_ROLES));

        abort_if($role === null, 403);

        return $role;
    }

    /** @param  array<int, string>  $roles */
    private function firstHeld(User $user, array $roles): ?string
    {
        return collect($roles)->first(fn (string $role) => $user->hasRole($role));
    }

    /**
     * Who may be named, for the page and the error message:
     * "a PMD Assistant Manager" or "a PMD Assistant Manager or the PMD Department Manager".
     *
     * @param  array<int, string>  $roles
     */
    private function oicWho(array $roles): string
    {
        return collect($roles)
            ->map(fn (string $role) => (in_array($role, User::SINGLETON_ROLES, true) ? 'the ' : 'a ') . User::roleLabel($role))
            ->join(', ', ' or ');
    }
}
