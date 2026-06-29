<?php

namespace App\Mail;

use App\Models\SavedReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SavedReportExportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $recipients
     */
    public function __construct(
        public SavedReport $report,
        public string $csv,
        public string $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Report: {$this->report->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.saved-report-export',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->csv, $this->filename)
                ->withMime('text/csv'),
        ];
    }
}
