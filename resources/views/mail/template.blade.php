<x-mail::message>
@foreach ($segments as $s)
@if ($s['type'] === 'button')
<x-mail::button :url="$s['url']">
{{ $s['text'] }}
</x-mail::button>

@else
{!! $s['text'] !!}

@endif
@endforeach
</x-mail::message>
