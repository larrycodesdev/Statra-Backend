<?php

namespace App\Mail\Store;

use App\Models\BandOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly BandOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('hello@statra.health', 'Statra'),
            subject: "Order Confirmed — {$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.store.order-confirmation');
    }
}
