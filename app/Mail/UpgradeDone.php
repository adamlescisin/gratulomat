<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Support\HtmlString;

/** Navýšení tarifu / doplňky zaplacené. Text: back-office → E-maily → Šablony („upgrade_done“). */
class UpgradeDone extends Mailable
{
    use UsesMailTemplate;

    public function __construct(public Order $order) {}

    public function templateKey(): string
    {
        return 'upgrade_done';
    }

    public function templateData(): array
    {
        $e = $this->order->event;
        $lines = collect($this->order->lines)->map(fn ($l) => '- '.e($l['name']).($l['qty'] > 1 ? ' × '.$l['qty'] : ''))->implode("\n");

        return [
            'vars' => [
                'nazev_udalosti' => $e->title, 'tarif' => config("gratulomat.plans.{$e->plan}.name"), 'limit_hostu' => (string) $e->limit('guests'),
                'archiv_do' => $e->archive_until->locale('cs')->isoFormat('LL'), 'polozky' => new HtmlString($lines),
                'cena' => $this->order->totalCzk(), 'cislo_objednavky' => $this->order->number,
            ],
            'flags' => ['test' => (bool) $this->order->is_test],
            'buttons' => ['administrace' => route('organizer.admin', $e)],
        ];
    }
}
