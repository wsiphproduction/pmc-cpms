<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lets the IT support account sign in as another user to see the system the
 * way they do. The real user's id rides along in the session so "Exit
 * impersonation" can sign them straight back in.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        abort_unless($actor->canImpersonate(), 403);

        if ($request->session()->has(User::IMPERSONATOR_SESSION_KEY)) {
            return redirect()->back()->withErrors(['error' => 'Exit the current impersonation first.']);
        }

        if ($user->is($actor)) {
            return redirect()->back()->withErrors(['error' => 'You are already signed in as yourself.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(User::IMPERSONATOR_SESSION_KEY, $actor->id);

        return redirect()->route('dashboard')->with('success', "You are now signed in as {$user->name}.");
    }

    public function leave(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull(User::IMPERSONATOR_SESSION_KEY);

        if (! $impersonatorId) {
            return redirect()->route('dashboard');
        }

        $impersonator = User::find($impersonatorId);

        // The real account went away mid-session: there is no one to return
        // to, so end the session rather than leave them as the other user.
        if (! $impersonator) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        Auth::login($impersonator);
        $request->session()->regenerate();

        return redirect()->route('users.index')->with('success', 'Impersonation ended. Welcome back.');
    }
}
