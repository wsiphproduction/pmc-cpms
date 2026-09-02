<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\ProjectNtp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the contractor their Notice to Proceed has been fully approved and
 * issued — sent by hand from the NTP hub once the Division Manager has signed,
 * so the project team controls exactly when the vendor is told.
 */
class NtpIssuedToVendor extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ProjectNtp $ntp,
        public readonly Project    $project,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Notice to Proceed {$this->ntp->ntp_no} – {$this->project->project_no} – {$this->project->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.ntp-issued',
            with: [
                'signatories' => $this->ntp->approvalTimeline(),
            ],
        );
    }
}
