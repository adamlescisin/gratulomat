@extends('backoffice.layout')
@section('title', 'Události')
@section('content')
<section class="panel">
  <p class="muted">Jako provozovatel otevřete administraci kterékoli události (pozvánky, moderace, videoprezentace). Nové události zatím zakládejte přes SSH: <code>gratulomat:event</code>, organizátora přidáte <code>gratulomat:organizer</code>.</p>
  @include('backoffice.partials.events-table', ['events' => $events])
</section>
@endsection
