<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EventReady extends Mailable
{
    public function __construct(public Order $order, public Event $event, public string $loginLink) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Událost „'.$this->event->title.'“ je připravená');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.event-ready');
    }
}
