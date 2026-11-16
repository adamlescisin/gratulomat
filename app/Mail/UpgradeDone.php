<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class UpgradeDone extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Navýšení události „'.$this->order->event->title.'“ je hotové');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.upgrade-done');
    }
}
