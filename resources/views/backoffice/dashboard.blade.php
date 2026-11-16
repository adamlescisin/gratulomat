@extends('backoffice.layout')
@section('title', 'Přehled')
@section('content')
<div class="kpis">
  @foreach ($kpis as [$label, $value])<div class="kpi"><b>{{ $value }}</b><span>{{ $label }}</span></div>@endforeach
</div>
<section class="panel">
  <h2>Poslední události</h2>
  @include('backoffice.partials.events-table', ['events' => $events])
  <p><a href="{{ route('backoffice.events') }}">Všechny události →</a></p>
</section>
@endsection
