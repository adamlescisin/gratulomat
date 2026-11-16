<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PaymentLink extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Odkaz k platbě – '.$this->order->config['name'].' (Gratulomat VIP)');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.payment-link');
    }
}
