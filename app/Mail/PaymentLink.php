<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\Order;
use Illuminate\Mail\Mailable;

/** Platební odkaz k VIP poptávce. Text: back-office → E-maily → Šablony („payment_link“). */
class PaymentLink extends Mailable
{
    use UsesMailTemplate;

    public function __construct(public Order $order) {}

    public function templateKey(): string
    {
        return 'payment_link';
    }

    public function templateData(): array
    {
        return [
            'vars' => [
                'nazev_udalosti' => $this->order->config['name'], 'cena' => $this->order->totalCzk(), 'cislo_objednavky' => $this->order->number,
                'platnost_do' => $this->order->expires_at?->timezone('Europe/Prague')->format('j. n. Y H:i'), 'adresa' => 'gratulomat.cz/'.$this->order->slug,
            ],
            'flags' => ['test' => (bool) $this->order->is_test],
            'buttons' => ['platba' => (string) $this->order->payment_url],
        ];
    }
}
