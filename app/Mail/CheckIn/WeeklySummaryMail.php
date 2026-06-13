<?php

namespace App\Mail\CheckIn;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklySummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly array  $stats,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'STATRA — Your weekly health summary');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.checkin.weekly-summary');
    }
}
