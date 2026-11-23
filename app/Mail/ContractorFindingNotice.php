<?php

namespace App\Mail;

use App\Models\ContractorFinding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a contractor about a non-compliance record and links them to the
 * page where they answer it. Contractors have no accounts: the unguessable
 * token in the link is what lets them in, scoped to this one record.
 */
class ContractorFindingNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ContractorFinding $finding) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Non-Compliance Record {$this->finding->control_no} – {$this->finding->project->project_no} – Corrective Action Required",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contractor-finding',
            with: [
                'project'   => $this->finding->project,
                'portalUrl' => $this->finding->portalUrl(),
            ],
        );
    }
}
