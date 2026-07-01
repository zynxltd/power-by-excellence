<?php

namespace App\Mail;

use App\Models\Buyer;
use App\Models\BuyerInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BuyerInvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Buyer $buyer,
        public BuyerInvoice $invoice,
        public string $platformName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->invoice->stripe_invoice_id} — {$this->platformName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.buyer-invoice',
        );
    }
}
