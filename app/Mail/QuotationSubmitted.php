<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\ProjectRfq;
use App\Models\ProjectRfqQuotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the project team a supplier has sent in a quotation through the portal.
 * Pairs with the in-app notification raised at the same moment.
 */
class QuotationSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ProjectRfq          $rfq,
        public readonly Project             $project,
        public readonly ProjectRfqQuotation $quotation,
        public readonly float               $grandTotal,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Quotation received – {$this->rfq->contractor_name} – {$this->project->project_no}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.quotation-submitted',
            with: [
                'hubUrl' => route('projects.hub.rfq', $this->project->id),
            ],
        );
    }
}
