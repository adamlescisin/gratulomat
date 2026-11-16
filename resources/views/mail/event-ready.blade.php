<x-mail::message>
# {{ $event->title }} je připravená 🎉

@if ($order->total_halere > 0)
Děkujeme za platbu **{{ $order->totalCzk() }}** (objednávka {{ $order->number }}, tarif {{ config("gratulomat.plans.{$order->plan}.name") }}).@if (! $order->is_test) Daňový doklad vám pošleme v samostatném e-mailu.@endif
@else
Událost běží v tarifu Ochutnávka. Na vyšší tarif můžete přejít kdykoli – nic se neztratí.
@endif

**Co dál**
1. Otevřete administraci a přidejte gratulanty – každý dostane osobní odkaz nebo QR kód.
2. Pošlete jim pozvánku (text je předvyplněný, stačí zkopírovat do WhatsAppu nebo e-mailu).
3. V den události zpřístupníte videoprezentaci jedním tlačítkem.

<x-mail::button :url="$loginLink">
Otevřít administraci
</x-mail::button>

Odkaz platí 7 dní a jen jednou. Další přihlášení: {{ route('login') }} – stačí zadat tento e-mail.

Stránka pro gratulanty: {{ url($event->slug) }}

@if ($order->is_test)
_Testovací objednávka – nic se neúčtovalo._
@endif

Gratulomat
</x-mail::message>
