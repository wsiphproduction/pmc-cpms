<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;
    use HasRoles {
        hasRole as protected holdsRole;
    }

    // ── Roles ─────────────────────────────────────────────────────────────
    //
    // Slugs are the stored Spatie role names; the labels below are what the UI
    // shows. `assistant_manager` is the engineering-side assistant manager who
    // covers for project engineers — distinct from the PMD approval roles.

    public const ROLE_REQUESTOR = 'requestor';
    public const ROLE_ENGINEER = 'approver';
    public const ROLE_ASSISTANT_MANAGER = 'assistant_manager';
    public const ROLE_PMD_ASST_MANAGER = 'pmd_asst_manager';
    public const ROLE_PMD_DEPT_MANAGER = 'pmd_dept_manager';
    public const ROLE_DIVISION_MANAGER = 'division_manager';
    public const ROLE_DIVISION_MANAGER_USER = 'division_manager_user';
    public const ROLE_ADMIN = 'admin';

    public const ROLE_LABELS = [
        self::ROLE_REQUESTOR => 'Department User',
        self::ROLE_ENGINEER => 'Project Engineer',
        self::ROLE_ASSISTANT_MANAGER => 'Assistant Manager',
        self::ROLE_PMD_ASST_MANAGER => 'PMD Assistant Manager',
        self::ROLE_PMD_DEPT_MANAGER => 'PMD Department Manager',
        self::ROLE_DIVISION_MANAGER => 'Division Manager',
        self::ROLE_DIVISION_MANAGER_USER => 'Division Manager User',
        self::ROLE_ADMIN => 'Admin',
    ];

    /** Roles only one active user may hold at a time. */
    public const SINGLETON_ROLES = [
        self::ROLE_PMD_DEPT_MANAGER,
        self::ROLE_DIVISION_MANAGER,
    ];

    /** The PMD/division sign-off roles: they approve, and otherwise only look. */
    public const APPROVAL_ROLES = [
        self::ROLE_PMD_ASST_MANAGER,
        self::ROLE_PMD_DEPT_MANAGER,
        self::ROLE_DIVISION_MANAGER,
    ];

    /** Roles that execute the work: they create and edit projects. */
    public const DELIVERY_ROLES = [
        self::ROLE_ENGINEER,
        self::ROLE_ASSISTANT_MANAGER,
        self::ROLE_ADMIN,
    ];

    /**
     * Everyone inside PMD — sees every project and request, unscoped by
     * department. Department users are the ones left out.
     */
    public const INTERNAL_ROLES = [
        self::ROLE_ENGINEER,
        self::ROLE_ASSISTANT_MANAGER,
        self::ROLE_PMD_ASST_MANAGER,
        self::ROLE_PMD_DEPT_MANAGER,
        self::ROLE_DIVISION_MANAGER,
        self::ROLE_ADMIN,
    ];

    /**
     * The text sizes the top bar's font size control steps through, as a
     * percentage of normal. Mirrored in resources/js/lib/font-scale.ts.
     */
    public const FONT_SCALES = [80, 90, 100, 110, 125, 150];

    /** The one account allowed to sign in as other users, for support. */
    public const IMPERSONATOR_EMAIL = 'it@philsagamining.com';

    /** Session key holding the real user's id while they are signed in as someone else. */
    public const IMPERSONATOR_SESSION_KEY = 'impersonator_id';

    public function canImpersonate(): bool
    {
        return strcasecmp((string) $this->email, self::IMPERSONATOR_EMAIL) === 0;
    }

    public static function roleLabel(?string $role): string
    {
        if ($role === null || $role === '') {
            return 'No role';
        }

        return self::ROLE_LABELS[$role] ?? ucwords(str_replace('_', ' ', $role));
    }

    /**
     * The name of whoever holds a role — the longest-standing holder when the
     * role is not a singleton — or null while nobody does.
     */
    public static function holderOf(string $role): ?string
    {
        return self::whereHas('roles', fn ($q) => $q->where('name', $role))
            ->oldest('id')
            ->value('name');
    }

    /**
     * Roles that carry full admin rights on top of their own: wherever the
     * admin role is asked for, holding one of these answers too.
     */
    public const ADMIN_EQUIVALENT_ROLES = [
        self::ROLE_DIVISION_MANAGER,
    ];

    /**
     * Spatie's check, widened so admin-equivalent roles pass every admin
     * check — route middleware, policies and controllers alike. Arrays and
     * pipe lists recurse through here, so they are covered as well.
     */
    public function hasRole($roles, ?string $guard = null): bool
    {
        if ($roles === self::ROLE_ADMIN && $this->holdsRole(self::ADMIN_EQUIVALENT_ROLES, $guard)) {
            return true;
        }

        return $this->holdsRole($roles, $guard);
    }

    /** Holds the admin role, or one that carries admin rights. */
    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /** A user holds exactly one role in this system. */
    public function primaryRole(): ?string
    {
        return $this->roles->first()?->name;
    }

    /** Department users are scoped to their own department's work. */
    public function isDepartmentUser(): bool
    {
        return ! $this->hasRole(self::INTERNAL_ROLES);
    }

    /** Holds one of the PMD/division sign-off roles. */
    public function isApprovalRole(): bool
    {
        return $this->hasRole(self::APPROVAL_ROLES);
    }

    /**
     * Signs NTPs from the For Approval portal rather than NTP Reviews — so a
     * step named to them is listed, and linked, there.
     */
    public function signsFromApprovalsPortal(): bool
    {
        return $this->isApprovalRole() || $this->isAdmin();
    }

    /**
     * Whether this division manager user has taken the given site: they then
     * sign NTPs for every project on it, whichever division owns the work.
     */
    public function claimsSite(?string $site): bool
    {
        return filled($site)
            && collect($this->sites ?? [])->contains(fn ($claimed) => strcasecmp((string) $claimed, (string) $site) === 0);
    }

    // ── Roster breaks ─────────────────────────────────────────────────────

    /** Breaks this user has taken or scheduled as the manager going away. */
    public function rosterBreaks(): HasMany
    {
        return $this->hasMany(RosterBreak::class, 'user_id');
    }

    /** Breaks during which this user covers for somebody else. */
    public function oicDuties(): HasMany
    {
        return $this->hasMany(RosterBreak::class, 'oic_user_id');
    }

    /** The break this user is away on today, if any. */
    public function activeRosterBreak(): ?RosterBreak
    {
        return $this->rosterBreaks()->activeOn()->with('oic')->first();
    }

    /** The break under which this user is somebody's OIC today, if any. */
    public function activeOicDuty(): ?RosterBreak
    {
        return $this->oicDuties()->activeOn()->with('user')->first();
    }

    /**
     * The roles this user covers as OIC today. Cached per request: the
     * approval chain asks on every step it authorises.
     *
     * @return array<int, string>
     */
    public function coveredRoles(): array
    {
        return $this->coveredRoles ??= $this->oicDuties()->activeOn()->pluck('role')->unique()->values()->all();
    }

    /**
     * Whether this user may act as the given role right now: either they
     * hold it, or they are the OIC covering it during a roster break. This is
     * what the approval chains check, so the cover applies wherever the role
     * is asked for rather than being wired in step by step.
     */
    public function actsAs(string $role): bool
    {
        return $this->hasRole($role) || in_array($role, $this->coveredRoles(), true);
    }

    /**
     * The approval roles this user acts as, in the chains' signing order —
     * their own first, then any they cover.
     *
     * @return array<int, string>
     */
    public function actingApprovalRoles(): array
    {
        return array_values(array_filter(self::APPROVAL_ROLES, fn (string $role) => $this->actsAs($role)));
    }

    /** @var array<int, string>|null */
    private ?array $coveredRoles = null;

    /** Approval-chain steps this user has been the one to settle. */
    public function approvalSteps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department',
        'division',
        'sites',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'tour_seen_at' => 'datetime',
            'password' => 'hashed',
            'sites' => 'array',
            'font_scale' => 'integer',
        ];
    }
}
