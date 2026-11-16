@extends('site.layout')
@section('title', 'Gratulomat – '.__('Přístupový kód'))
@section('content')
    <h1>{{ __('Přístupový kód') }}</h1>
    <form method="post" action="/pristup">
        @csrf
        <p><input name="code" autocomplete="off" autofocus aria-label="{{ __('Přístupový kód') }}" placeholder="BETA-XXXX"></p>
        @error('code')<p class="error">{{ $message }}</p>@enderror
        <p><button class="btn" type="submit">{{ __('Pokračovat') }}</button></p>
    </form>
@endsection
