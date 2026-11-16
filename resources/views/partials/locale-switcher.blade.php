{{-- Přepínač jazyka rozhraní pro hosty. Názvy jazyků vždy ve vlastním jazyce. --}}
@php($names = ['cs' => 'Čeština', 'en' => 'English', 'de' => 'Deutsch'])
<nav class="locale-switcher" aria-label="{{ __('Jazyk') }}">
    @foreach (config('gratulomat.locales.guest') as $code)
        <form method="post" action="{{ route('locale.switch', $code) }}">
            @csrf
            <button type="submit" lang="{{ $code }}" @if (app()->getLocale() === $code) aria-current="true" @endif>
                {{ $names[$code] ?? strtoupper($code) }}
            </button>
        </form>
    @endforeach
</nav>
