<?php

namespace App\Mail;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketResolvedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public User $resolvedBy,
        public User $recipient,
        public string $ticketUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Support ticket resolved - '.$this->ticket->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: view('emails.support-ticket-resolved', [
                'ticket' => $this->ticket,
                'resolvedBy' => $this->resolvedBy,
                'recipient' => $this->recipient,
                'ticketUrl' => $this->ticketUrl,
            ])->render(),
        );
    }
}
