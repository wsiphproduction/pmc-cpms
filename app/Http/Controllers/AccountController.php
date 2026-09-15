<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Mail\AccountUpdated;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('account/index', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function updateProfile(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        $changes = [];
        if ($user->isDirty('name')) {
            $changes[] = ['Name', $user->getOriginal('name'), $user->name];
        }
        if ($user->isDirty('email')) {
            $changes[] = ['Email Address', $user->getOriginal('email'), $user->email];
            $user->email_verified_at = null;
        }

        $previousEmail = $user->getOriginal('email');

        $user->save();

        if ($changes) {
            // The old address hears about an email change too, so a hijacked
            // account cannot silently move itself out of reach of its owner.
            $recipients = array_unique(array_filter([$previousEmail, $user->email]));
            $this->notifyAccountChange($user, $changes, $recipients);
        }

        return to_route('account.edit')->with('success', 'Account details updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->notifyAccountChange($user, [['Password', '', 'Changed']], [$user->email]);

        return to_route('account.edit')->with('success', 'Password updated.');
    }

    /**
     * Queue an email to the account holder about what changed. A queue outage must not
     * undo the save, which has already happened.
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $changes
     * @param  array<int, string>  $recipients
     */
    private function notifyAccountChange(User $user, array $changes, array $recipients): void
    {
        $changedAt = now()->format('F d, Y h:i A');

        foreach ($recipients as $email) {
            if (! filled($email)) {
                continue;
            }

            try {
                Mail::to($email)->send(new AccountUpdated($user, $changes, $changedAt));
            } catch (\Throwable $e) {
                Log::error("Account update email to {$email} could not be queued for user #{$user->id}: " . $e->getMessage());
            }
        }
    }
}
