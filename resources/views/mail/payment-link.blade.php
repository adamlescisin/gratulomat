<x-mail::message>
# Vaše VIP událost je připravená k objednání

Děkujeme za poptávku **{{ $order->config['name'] }}**. Jak jsme se domluvili, cena tarifu VIP je **{{ $order->totalCzk() }}** včetně DPH (objednávka {{ $order->number }}).

<x-mail::button :url="$order->payment_url">
Zaplatit {{ $order->totalCzk() }}
</x-mail::button>

Odkaz platí do {{ $order->expires_at->timezone('Europe/Prague')->format('j. n. Y H:i') }}. Hned po zaplacení událost založíme na adrese gratulomat.cz/{{ $order->slug }} a pošleme vám odkaz do administrace{{ $order->is_test ? '' : ' i daňový doklad' }}.

@if ($order->is_test)
_Testovací objednávka – použijte testovací kartu 4242 4242 4242 4242._
@endif

Gratulomat
</x-mail::message>
