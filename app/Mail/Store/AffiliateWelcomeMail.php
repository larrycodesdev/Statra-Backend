<?php

namespace App\Mail\Store;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AffiliateWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('hello@statrahealth.com', 'Statra'),
            subject: 'Welcome to the STATRA Affiliate Program!',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.store.affiliate-welcome');
    }
}
