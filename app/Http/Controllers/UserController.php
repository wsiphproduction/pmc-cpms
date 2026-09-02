<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('users/index', [
            'users' => User::with('roles')->latest()->get()->map(fn (User $user) => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'department' => $user->department,
                'role'       => $user->roles->first()?->name,
                'created_at' => $user->created_at,
            ]),
            'trashedUsers' => User::onlyTrashed()->with('roles')->latest('deleted_at')->get()->map(fn (User $user) => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->roles->first()?->name,
                'deleted_at' => $user->deleted_at,
            ]),
            'roles' => Role::orderBy('name')->pluck('name'),
            // Roles only one person may hold, and who holds them now, so the
            // form can say so before the save is rejected.
            'singletonRoles' => collect(User::SINGLETON_ROLES)->mapWithKeys(fn (string $role) => [
                $role => User::whereHas('roles', fn ($q) => $q->where('name', $role))
                    ->value('name'),
            ]),
            'roleLabels' => User::ROLE_LABELS,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['name', 'description'])->map(fn (Department $row) => [
                'value'        => (string) $row->name,
                'label'        => $row->description ? "{$row->name} — {$row->description}" : (string) $row->name,
                'displayLabel' => (string) $row->name,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|max:255|unique:users,email',
            'password'   => ['required', Password::min(8)],
            'role'       => ['required', 'string', 'exists:roles,name', $this->singletonRoleRule()],
            'department' => [Rule::requiredIf(fn () => $request->input('role') === User::ROLE_REQUESTOR), 'nullable', 'string', 'max:191'],
        ]);

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'department' => $data['role'] === User::ROLE_REQUESTOR ? ($data['department'] ?? null) : null,
        ]);

        $user->assignRole($data['role']);

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|max:255|unique:users,email,' . $user->id,
            'role'       => ['required', 'string', 'exists:roles,name', $this->singletonRoleRule($user)],
            'department' => [Rule::requiredIf(fn () => $request->input('role') === User::ROLE_REQUESTOR), 'nullable', 'string', 'max:191'],
        ]);

        $user->update([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'department' => $data['role'] === User::ROLE_REQUESTOR ? ($data['department'] ?? null) : null,
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return redirect()->back()->with('success', 'Password reset successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return redirect()->back()->with('success', 'User moved to trash.');
    }

    public function restore(int $id)
    {
        $user = User::onlyTrashed()->findOrFail($id);

        // Restoring must not put a second holder into a single-holder role.
        $role = $user->roles->first()?->name;
        if ($role && in_array($role, User::SINGLETON_ROLES, true) && $this->singletonHolder($role)) {
            return redirect()->back()->withErrors([
                'error' => User::roleLabel($role) . ' is already held by ' . $this->singletonHolder($role)
                    . '. Reassign that user first, then restore this one.',
            ]);
        }

        $user->restore();

        return redirect()->back()->with('success', 'User restored successfully.');
    }

    public function forceDelete(int $id)
    {
        $user = User::onlyTrashed()->findOrFail($id);

        if ($user->id === auth()->id()) {
            return redirect()->back()->withErrors(['error' => 'You cannot permanently delete your own account.']);
        }

        $user->forceDelete();

        return redirect()->back()->with('success', 'User permanently deleted.');
    }

    /**
     * Guards the roles limited to one holder (PMD Department Manager, Division
     * Manager). Passing the user being edited lets them keep their own role.
     */
    private function singletonRoleRule(?User $editing = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($editing) {
            if (! in_array($value, User::SINGLETON_ROLES, true)) {
                return;
            }

            $holder = User::whereHas('roles', fn ($q) => $q->where('name', $value))
                ->when($editing, fn ($q) => $q->whereKeyNot($editing->id))
                ->first();

            if ($holder) {
                $fail(User::roleLabel($value) . " is limited to one user and is currently held by {$holder->name}.");
            }
        };
    }

    private function singletonHolder(string $role): ?string
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $role))->value('name');
    }
}
