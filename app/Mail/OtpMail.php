<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $otp,
        public readonly string $name,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('hello@statra.health', 'Statra Health'),
            subject: 'Your Statra Verification Code',
        );
    }

    public function content(): Content
    {
        return new Content(html: 'emails.otp');
    }
}
