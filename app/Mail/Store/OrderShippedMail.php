<?php

namespace App\Mail\Store;

use App\Models\BandOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShippedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BandOrder $order,
        public readonly string    $trackingNumber,
        public readonly string    $courier,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('hello@statra.health', 'Statra'),
            subject: "Your STATRA Band is on its way! 📦",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.store.order-shipped');
    }
}
