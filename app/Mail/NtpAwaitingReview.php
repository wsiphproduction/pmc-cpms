<?php

namespace App\Mail;

use App\Models\ProjectNtp;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a project-side reviewer — the department user, or the division manager
 * user over them — that an NTP has reached their step. They seldom sit in the
 * system, so the in-app bell alone can leave an NTP waiting unseen.
 */
class NtpAwaitingReview extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** The office this reviewer signs for, fixed now — the chain may move before the mail goes out. */
    public readonly string $stepLabel;

    /** Absolute link to NTP Reviews, built from the current request's host. */
    public readonly string $reviewUrl;

    public function __construct(
        public readonly ProjectNtp $ntp,
        public readonly User       $reviewer,
    ) {
        $this->stepLabel = User::roleLabel($ntp->currentApprovalRole());
        $this->reviewUrl = route('ntp-reviews.index');
    }

    public function envelope(): Envelope
    {
        $project = $this->ntp->project;

        return new Envelope(
            subject: "For your approval: NTP {$this->ntp->ntp_no} – {$project->project_no} – {$project->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.ntp-awaiting-review',
            with: [
                'project' => $this->ntp->project,
            ],
        );
    }
}
