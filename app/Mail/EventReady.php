<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Mail\Mailable;

/** Událost je založená – odkaz do administrace. Text: back-office → E-maily → Šablony („event_ready“). */
class EventReady extends Mailable
{
    use UsesMailTemplate;

    public function __construct(public Order $order, public Event $event, public string $loginLink) {}

    public function templateKey(): string
    {
        return 'event_ready';
    }

    public function templateData(): array
    {
        return [
            'vars' => [
                'nazev_udalosti' => $this->event->title, 'cena' => $this->order->totalCzk(), 'cislo_objednavky' => $this->order->number,
                'tarif' => config("gratulomat.plans.{$this->order->plan}.name"), 'prihlaseni' => route('login'), 'stranka_hostu' => url($this->event->slug),
            ],
            'flags' => ['zaplaceno' => $this->order->total_halere > 0, 'test' => (bool) $this->order->is_test],
            'buttons' => ['administrace' => $this->loginLink],
        ];
    }
}
