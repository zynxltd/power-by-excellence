<?php

namespace App\Mail;

use App\Models\Buyer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BuyerLowCreditMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Buyer $buyer,
        public float $balance,
        public float $threshold,
        public string $currency,
        public string $platformName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Low credit alert — {$this->buyer->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.buyer-low-credit',
        );
    }
}
