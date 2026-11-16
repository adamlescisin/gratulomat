@php($brand = \App\Support\MailBrand::class)
<x-mail::layout>
{{-- Hlavička: logo z back-office, jinak název --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
@if ($brand::logoUrl())
<img src="{{ $brand::logoUrl() }}" width="{{ $brand::logoWidth() }}" style="width: {{ $brand::logoWidth() }}px; max-width: 100%; height: auto; border: 0;" alt="{{ $brand::fromName() }}">
@else
<span style="color: #1C1633; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">{{ $brand::fromName() }}</span>
@endif
</x-mail::header>
</x-slot:header>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
{!! nl2br(e($brand::footer()), false) !!}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
