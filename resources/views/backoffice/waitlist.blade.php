@extends('backoffice.layout')
@section('title', 'Čekací listina')
@section('content')
<section class="panel">
  <div class="row" style="justify-content:space-between;align-items:center">
    <span class="muted">{{ $entries->count() }} zájemců · upozornění na nové chodí na {{ config('gratulomat.notify_email') }}</span>
    <a class="b b--sm b--ghost" href="{{ route('backoffice.waitlist.csv') }}">⬇ Export CSV</a>
  </div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>E-mail</th><th>Přihlášen</th><th>Pozván</th><th></th></tr></thead>
    <tbody>
    @forelse ($entries as $w)
      <tr>
        <td><a href="mailto:{{ $w->email }}">{{ $w->email }}</a></td>
        <td>{{ $w->created_at->timezone('Europe/Prague')->format('j. n. Y H:i') }}</td>
        <td>{{ $w->invited_at?->timezone('Europe/Prague')->format('j. n. Y') ?? '—' }}</td>
        <td><form method="post" action="{{ route('backoffice.waitlist.delete', $w) }}" onsubmit="return confirm('Smazat {{ $w->email }} z čekací listiny?')">@csrf @method('DELETE')<button class="b b--sm b--ghost">Smazat</button></form></td>
      </tr>
    @empty
      <tr><td colspan="4" class="muted">Zatím nikdo.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@endsection
