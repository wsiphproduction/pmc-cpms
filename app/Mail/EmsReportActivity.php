<?php

namespace App\Mail;

use App\Models\EmsSurveyReport;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * The mailed copy of an EMS Survey Report notification — sent to MEPEO,
 * submitted, approved, reviewed, noted, returned. MEPEO seldom sits in the
 * system, so the bell alone could leave a report waiting unseen.
 */
class EmsReportActivity extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Where the report stood when this happened, fixed now — it may move before the mail goes out. */
    public readonly string $statusLabel;

    /** Who the chain was waiting on then, if anyone. */
    public readonly ?string $awaitingLabel;

    public function __construct(
        public readonly EmsSurveyReport $report,
        public readonly User            $recipient,
        public readonly string          $headline,
        // Absolute, built from the current request's host.
        public readonly string          $url,
    ) {
        $this->statusLabel = $report->statusLabel();

        $role = $report->status === EmsSurveyReport::STATUS_FOR_APPROVAL ? $report->currentApprovalRole() : null;

        $this->awaitingLabel = $role ? User::roleLabel($role) : null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Str::limit("EMS Survey Report {$this->report->control_no} – {$this->report->project?->project_no}: {$this->headline}", 180),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.ems-report-activity',
            with: [
                'project' => $this->report->project,
            ],
        );
    }
}
