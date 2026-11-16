<x-mail::message>
# {{ ['vip' => 'VIP poptávka', 'upgrade' => 'Navýšení', 'manual' => 'Ruční založení'][$order->kind] ?? 'Nová událost' }} {{ $order->number }}@if ($order->is_test) (TEST)@endif

- Událost: **{{ $order->config['name'] ?? $order->slug }}**@isset($order->config['occasion']) ({{ \App\Domain\Orders\WizardConfig::OCCASIONS[$order->config['occasion']][1] ?? '' }}, {{ $order->config['date'] ?? '' }})@endisset
- Adresa: {{ url($order->slug) }}
- Tarif: {{ config("gratulomat.plans.{$order->plan}.name") }} · {{ $order->totalCzk() }}
@isset($order->config['guests'])
- Gratulantů: {{ $order->config['guests'] }}
@endisset
- E-mail: {{ $order->email }}
@if ($order->company)
- Firma: {{ $order->company['name'] }}, IČO {{ $order->company['ico'] }}
@endif
@if ($order->openTodo())

**K vyřízení:**
@foreach ($order->openTodo() as $t)
- {{ $t['name'] }}
@endforeach
@endif

<x-mail::button :url="route('backoffice.order', $order)">
Otevřít objednávku
</x-mail::button>
</x-mail::message>
