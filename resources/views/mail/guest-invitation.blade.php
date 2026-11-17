<x-mail::message>
@if ($reminder)
# Ještě je čas přispět

Ahoj {{ implode(' a ', $household->names) }}, sběr vzpomínek a přání pro událost **{{ $event->title }}** se uzavře **{{ $deadline }}**. Pokud máte historku, fotku nebo vzkaz, budeme moc rádi.
@else
{!! nl2br(e($text), false) !!}
@endif

<x-mail::button :url="$url">
{{ $event->isFuneral() ? 'Přidat vzpomínku' : 'Přispět vzpomínkou' }}
</x-mail::button>

@unless ($event->isFuneral())
<small>Gratulomat je nová služba, pomocí které můžete sbírat vzpomínky, zážitky, gratulace nebo přání pro události, oslavy nebo rozlučky. Můžete použít text, fotky, videa nebo vlastní hlas a my za vás připravíme jedinečnou prezentaci. Až se podělíte o své přání a vzpomínky, vyzkoušejte si to sami a vytvořte si svůj vlastní Gratulomat pro vaši vlastní akci.</small>

@endunless

<small>Tento odkaz je jen pro vás, neposílejte jej prosím dále. Nechcete další e-maily k této události? [Odhlásit]({{ $unsubscribe }})</small>
</x-mail::message>
