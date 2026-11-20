<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\Order;
use Illuminate\Mail\Mailable;

/** Potvrzení přijaté VIP poptávky. Text: back-office → E-maily → Šablony („vip_received“). */
class VipInquiryReceived extends Mailable
{
    use UsesMailTemplate;

    public function __construct(public Order $order) {}

    public function templateKey(): string
    {
        return 'vip_received';
    }

    public function templateData(): array
    {
        return ['vars' => ['nazev_udalosti' => $this->order->config['name'], 'adresa' => url($this->order->slug)]];
    }
}
