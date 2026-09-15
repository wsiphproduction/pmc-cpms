<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a user their account details or password were changed from the
 * account page, so an edit they did not make does not go unnoticed. When the
 * email address itself changed, the notice goes to both the old and new
 * addresses.
 */
class AccountUpdated extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $changes  [label, before, after] rows
     */
    public function __construct(
        public readonly User   $user,
        public readonly array  $changes,
        public readonly string $changedAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your account was updated – ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account-updated',
            with: [
                'accountUrl' => route('account.edit'),
            ],
        );
    }
}
