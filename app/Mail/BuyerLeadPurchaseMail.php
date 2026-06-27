<?php

namespace App\Mail;

use App\Models\Buyer;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BuyerLeadPurchaseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Buyer $buyer,
        public Lead $lead,
        public float $revenue,
        public string $currency,
        public string $portalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Lead purchased - '.$this->lead->uuid,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: view('emails.buyer-lead-purchase', [
                'buyer' => $this->buyer,
                'lead' => $this->lead,
                'revenue' => $this->revenue,
                'currency' => $this->currency,
                'portalUrl' => $this->portalUrl,
            ])->render(),
        );
    }
}
