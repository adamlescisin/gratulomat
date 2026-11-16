@extends('backoffice.layout')
@section('title', 'Události')
@section('content')
<section class="panel">
  <div class="row" style="justify-content:space-between;align-items:center;gap:16px">
    <p class="muted" style="margin:0">Jako provozovatel otevřete administraci kterékoli události (pozvánky, moderace, videoprezentace). Novou událost bez platby založíte v průvodci – v posledním kroku zaškrtněte <strong>Založit ručně bez platby</strong>.</p>
    <a class="b b--sm" href="{{ route('wizard') }}">+ Nová událost</a>
  </div>
  @include('backoffice.partials.events-table', ['events' => $events])
</section>
@endsection
