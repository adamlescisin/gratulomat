<x-mail::message>
# {{ $order->kind === 'vip' ? 'VIP poptávka' : 'Nová událost' }} {{ $order->number }}@if ($order->is_test) (TEST)@endif

- Událost: **{{ $order->config['name'] }}** ({{ \App\Domain\Orders\WizardConfig::OCCASIONS[$order->config['occasion']][1] ?? '' }}, {{ $order->config['date'] }})
- Adresa: {{ url($order->slug) }}
- Tarif: {{ config("gratulomat.plans.{$order->plan}.name") }} · {{ $order->totalCzk() }}
- Gratulantů: {{ $order->config['guests'] }}
- E-mail: {{ $order->email }}
@if ($order->company)
- Firma: {{ $order->company['name'] }}, IČO {{ $order->company['ico'] }}
@endif

<x-mail::button :url="url('/backoffice/udalosti')">
Back-office
</x-mail::button>
</x-mail::message>
