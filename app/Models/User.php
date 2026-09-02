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
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

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
    public const ROLE_ADMIN = 'admin';

    public const ROLE_LABELS = [
        self::ROLE_REQUESTOR => 'Department User',
        self::ROLE_ENGINEER => 'Project Engineer',
        self::ROLE_ASSISTANT_MANAGER => 'Assistant Manager',
        self::ROLE_PMD_ASST_MANAGER => 'PMD Assistant Manager',
        self::ROLE_PMD_DEPT_MANAGER => 'PMD Department Manager',
        self::ROLE_DIVISION_MANAGER => 'Division Manager',
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

    public static function roleLabel(?string $role): string
    {
        if ($role === null || $role === '') {
            return 'No role';
        }

        return self::ROLE_LABELS[$role] ?? ucwords(str_replace('_', ' ', $role));
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
            'password' => 'hashed',
        ];
    }
}
