<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => ['required', Password::min(8)],
            'role'     => 'required|string|exists:roles,name',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($data['role']);

        return redirect()->back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'role'  => 'required|string|exists:roles,name',
        ]);

        $user->update([
            'name'  => $data['name'],
            'email' => $data['email'],
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
}
