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
 * Tells a signatory — from the PMD Assistant Manager through to the division
 * manager user over the project's department — that an NTP has reached their
 * step. Most seldom sit in the system, so the in-app bell alone can leave an
 * NTP waiting unseen.
 */
class NtpAwaitingReview extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** The office this reviewer signs for, fixed now — the chain may move before the mail goes out. */
    public readonly string $stepLabel;

    /** The page the reviewer signs from: the For Approval portal, or NTP Reviews. */
    public readonly string $pageLabel;

    /** Absolute link to that page, built from the current request's host. */
    public readonly string $reviewUrl;

    /**
     * @param  int|null  $waitingDays  Set when this is a reminder: how long the
     *                                 NTP has already waited at this step.
     */
    public function __construct(
        public readonly ProjectNtp $ntp,
        public readonly User       $reviewer,
        public readonly ?int       $waitingDays = null,
    ) {
        $portal = $reviewer->signsFromApprovalsPortal();

        $this->stepLabel = User::roleLabel($ntp->currentApprovalRole());
        $this->pageLabel = $portal ? 'For Approval' : 'NTP Reviews';
        $this->reviewUrl = route($portal ? 'approvals.index' : 'ntp-reviews.index');
    }

    public function envelope(): Envelope
    {
        $project = $this->ntp->project;

        $prefix = $this->waitingDays !== null
            ? "Reminder ({$this->waitingDays} days pending)"
            : 'For your approval';

        return new Envelope(
            subject: "{$prefix}: NTP {$this->ntp->ntp_no} – {$project->project_no} – {$project->title}",
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
