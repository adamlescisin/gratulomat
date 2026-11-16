{{-- Přepínač jazyka rozhraní hosta (obsah události se nepřekládá). --}}
@php($names = ['cs' => 'CS', 'en' => 'EN', 'de' => 'DE'])
<nav class="lang" aria-label="{{ __('guest.language') }}">
    @foreach (config('gratulomat.locales.guest') as $code)
        <form method="post" action="{{ route('locale.switch', $code) }}">@csrf
            <button type="submit" lang="{{ $code }}" @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $names[$code] ?? strtoupper($code) }}</button>
        </form>
    @endforeach
</nav>
