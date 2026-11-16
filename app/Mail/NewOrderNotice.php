<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Upozornění pro provozovatele: nová objednávka / VIP poptávka. */
class NewOrderNotice extends Mailable
{
    public function __construct(public Order $order, public ?Event $event = null) {}

    public function envelope(): Envelope
    {
        $what = $this->order->kind === 'vip' ? 'VIP poptávka' : 'Nová událost';

        return new Envelope(subject: ($this->order->is_test ? '[TEST] ' : '')."$what {$this->order->number}: {$this->order->config['name']} ({$this->order->totalCzk()})");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.new-order');
    }
}
