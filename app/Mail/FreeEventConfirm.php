<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class FreeEventConfirm extends Mailable
{
    public function __construct(public Order $order, public string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Potvrďte založení události „'.$this->order->config['name'].'“');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.free-confirm');
    }
}
