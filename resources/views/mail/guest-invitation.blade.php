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

<small>Odkaz je jen pro vás. Nechcete další e-maily k této události? [Odhlásit]({{ $unsubscribe }})</small>
</x-mail::message>
