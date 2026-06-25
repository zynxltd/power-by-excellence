<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $plainPassword,
        public string $portalUrl,
        public string $platformName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->platformName} portal login",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: view('emails.portal-credentials', [
                'user' => $this->user,
                'password' => $this->plainPassword,
                'portalUrl' => $this->portalUrl,
                'platformName' => $this->platformName,
            ])->render(),
        );
    }
}
