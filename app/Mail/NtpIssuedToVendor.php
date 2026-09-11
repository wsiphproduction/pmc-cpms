<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\ProjectNtp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Tells the contractor their Notice to Proceed has been fully approved and
 * issued — sent by hand from the NTP hub once the Division Manager has signed,
 * so the project team controls exactly when the vendor is told.
 */
class NtpIssuedToVendor extends Mailable
{
    use Queueable, SerializesModels;

    /** How long the vendor's link to the approved form stays open. */
    public const LINK_VALID_DAYS = 5;

    /** Fixed when the mail is built, so the email and its link agree on the deadline. */
    public readonly Carbon $documentExpiresAt;

    /** Absolute, signed link to the approved NTP form. */
    public readonly string $documentUrl;

    public function __construct(
        public readonly ProjectNtp $ntp,
        public readonly Project    $project,
    ) {
        $this->documentExpiresAt = now()->addDays(self::LINK_VALID_DAYS);
        // Signed over the path only (the route checks `signed:relative`), then
        // made absolute from the current request so it carries whatever host
        // and sub-path the app is actually served from.
        $this->documentUrl = url(URL::temporarySignedRoute(
            'ntp.vendor-document', $this->documentExpiresAt, ['ntp' => $ntp->id], absolute: false,
        ));
    }

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
                'signatories'       => $this->ntp->approvalTimeline(),
                'documentUrl'       => $this->documentUrl,
                'documentExpiresAt' => $this->documentExpiresAt,
                'linkValidDays'     => self::LINK_VALID_DAYS,
            ],
        );
    }
}
