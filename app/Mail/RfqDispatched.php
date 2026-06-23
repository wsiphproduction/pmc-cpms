<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\ProjectRfq;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RfqDispatched extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ProjectRfq $rfq,
        public readonly Project    $project,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Request for Quotation – {$this->project->project_no} – {$this->project->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.rfq-dispatched',
        );
    }
}
