<?php

namespace App\Mail;

use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * The mailed copy of a project request notification — submitted, reviewed,
 * held, endorsed, approved, rejected, commented on, assigned. It says what
 * the bell says, so people who seldom sign in still hear of it.
 */
class ProjectRequestActivity extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Where the request stood when this happened, fixed now — it may move before the mail goes out. */
    public readonly string $statusLabel;

    /** Who the chain was waiting on then, if anyone. */
    public readonly ?string $awaitingLabel;

    public function __construct(
        public readonly ProjectRequest $projectRequest,
        public readonly User           $recipient,
        public readonly string         $headline,
        // Absolute, built from the current request's host.
        public readonly string         $url,
    ) {
        $this->statusLabel = self::STATUS_LABELS[$projectRequest->status] ?? ucfirst((string) $projectRequest->status);

        $role = in_array($projectRequest->status, ['pending', 'in_approval', 'hold'], true)
            ? $projectRequest->currentApprovalRole()
            : null;

        $this->awaitingLabel = $role ? User::roleLabel($role) : null;
    }

    /** As the Requests pages label them. */
    private const STATUS_LABELS = [
        'pending'     => 'For Approval',
        'in_approval' => 'In Approval',
        'hold'        => 'On Hold',
        'approved'    => 'Approved',
        'rejected'    => 'Rejected',
        'ongoing'     => 'Ongoing',
        'completed'   => 'Completed',
    ];

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Str::limit("{$this->projectRequest->request_no} – {$this->projectRequest->title}: {$this->headline}", 180),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.project-request-activity',
            with: [
                'requester' => $this->projectRequest->requester,
            ],
        );
    }
}
