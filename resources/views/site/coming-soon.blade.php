@extends('site.layout')
@section('title', 'Gratulomat – '.__('Připravujeme'))
@section('content')
    <h1>Gratulomat</h1>
    <p><strong>{{ __('Gratulomat se právě připravuje.') }}</strong></p>
    <p>{{ __('Sbírejte vzpomínky, fotky a přání od všech hostů a promítněte je jako videoprezentaci.') }}</p>
    {{-- Čekací listina přibude s tabulkou waitlist. --}}
    <p><a href="{{ route('access') }}">{{ __('Mám přístupový kód') }}</a></p>
@endsection
