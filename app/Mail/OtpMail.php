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
            from: new Address('hello@statrahealth.com', 'Statra'),
            subject: 'Your SCD Wellness OTP Code',
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.otp');
    }
}
