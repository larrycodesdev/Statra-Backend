<?php

namespace App\Mail\CheckIn;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('hello@statra.health', 'Statra'),
            subject: 'STATRA — Time for your daily check-in',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.checkin.daily-reminder');
    }
}
