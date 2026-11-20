<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\Order;
use Illuminate\Mail\Mailable;

/** Potvrzení založení události v tarifu Ochutnávka. Text: back-office → E-maily → Šablony („free_confirm“). */
class FreeEventConfirm extends Mailable
{
    use UsesMailTemplate;

    public function __construct(public Order $order, public string $link) {}

    public function templateKey(): string
    {
        return 'free_confirm';
    }

    public function templateData(): array
    {
        return ['vars' => ['nazev_udalosti' => $this->order->config['name']], 'buttons' => ['potvrzeni' => $this->link]];
    }
}
