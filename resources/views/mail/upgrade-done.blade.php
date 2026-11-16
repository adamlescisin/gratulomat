<x-mail::message>
# Hotovo, děkujeme!

Událost **{{ $order->event->title }}** má teď tarif **{{ config("gratulomat.plans.{$order->event->plan}.name") }}**.

- Hosté: až {{ $order->event->limit('guests') }}
- Archiv do: {{ $order->event->archive_until->locale('cs')->isoFormat('LL') }}
@foreach ($order->lines as $l)
- {{ $l['name'] }}{{ $l['qty'] > 1 ? ' × '.$l['qty'] : '' }}
@endforeach

Zaplaceno {{ $order->totalCzk() }} (objednávka {{ $order->number }}).@unless ($order->is_test) Daňový doklad pošleme zvlášť.@endunless

<x-mail::button :url="route('organizer.admin', $order->event)">
Otevřít administraci
</x-mail::button>

Gratulomat
</x-mail::message>
