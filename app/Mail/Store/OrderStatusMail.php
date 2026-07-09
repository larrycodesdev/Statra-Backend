<?php

namespace App\Mail\Store;

use App\Models\BandOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly BandOrder $order) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->order->status) {
            'processing' => 'Your STATRA order is being processed',
            'packed'     => 'Your STATRA order has been packed',
            'dispatched' => 'Your STATRA order has been dispatched',
            'in_transit' => 'Your STATRA order is in transit',
            'shipped'    => 'Your STATRA Band is on its way! 📦',
            'delivered'  => 'Your STATRA Band has been delivered! 🎉',
            'delayed'    => 'Update on your STATRA order',
            'cancelled'  => 'Your STATRA order has been cancelled',
            default      => 'Update on your STATRA order',
        };

        return new Envelope(
            from: new Address('hello@statra.health', 'Statra'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.store.order-status');
    }
}
