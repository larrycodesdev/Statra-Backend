<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('hello@statrahealth.com', 'Statra'),
            subject: 'New Contact Message — ' . $this->contact->full_name,
            replyTo: [$this->contact->email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-notification');
    }
}
