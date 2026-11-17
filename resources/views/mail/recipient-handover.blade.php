<x-mail::message>
@if ($event->isFuneral())
# Vzpomínky, které jsme pro vás sesbírali

Dobrý den, {{ $who }} se podělil{{ $suffix }} o vzpomínky, fotografie a vzkazy k rozloučení **{{ $event->title }}**. Teď patří vám.
@else
# Ahoj {{ $event->form('voc') ?: $event->recipient_name }}!

{{ $who }} pro vás poskládal{{ $suffix }} vzpomínky, fotky a přání k události **{{ $event->title }}**. Teď patří vám.
@endif

@if (! empty($h['message']))
<x-mail::panel>
{!! nl2br(e($h['message']), false) !!}

— {{ $sender->name ?: $sender->email }}
</x-mail::panel>
@endif

<x-mail::button :url="$url">
{{ $event->isFuneral() ? 'Otevřít vzpomínky' : 'Otevřít dárek' }}
</x-mail::button>

Najdete tam videoprezentaci a všechny vzpomínky{{ $event->isFuneral() ? '' : ' včetně soukromých přání, která byla určená jen vám' }}.@if ($offline) Videoprezentaci si můžete stáhnout a mít ji napořád, i bez internetu.@endif

@if ($archive)
Stránka je dostupná do **{{ $archive }}**{{ $offline ? ' – stáhněte si ji prosím do té doby' : '' }}.
@endif

<small>Odkaz je jen pro vás, nepřeposílejte ho prosím. Na tento e-mail můžete odpovědět – odpověď dostane {{ $sender->name ?: 'organizátor' }}.</small>
</x-mail::message>
