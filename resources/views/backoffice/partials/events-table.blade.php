<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th>Událost</th><th>Typ / tarif</th><th>Datum</th><th>Stav</th><th>Hosté</th><th>Vzpomínky</th><th></th></tr></thead>
  <tbody>
  @forelse ($events as $e)
    <tr>
      <td><strong>{{ $e->title }}</strong> @if ($e->is_test)<span class="tag">TEST</span>@endif<br><span class="muted">/{{ $e->slug }}@if ($e->relationLoaded('organizers') && $e->organizers->isNotEmpty()) · {{ $e->organizers->pluck('email')->join(', ') }}@endif</span></td>
      <td>{{ $e->type }}<br><span class="muted">{{ config("gratulomat.plans.{$e->plan}.name", $e->plan) }}</span></td>
      <td>{{ $e->event_date->timezone('Europe/Prague')->format('j. n. Y') }}</td>
      <td>{{ $e->status }}</td>
      <td>{{ $e->households_count }}</td>
      <td>{{ $e->memories_count }}</td>
      <td style="white-space:nowrap"><a class="b b--sm" href="{{ route('backoffice.event', $e) }}">Upravit</a> <a class="b b--sm b--ghost" href="{{ route('organizer.admin', $e) }}">Administrace</a> <a class="b b--sm b--ghost" href="{{ route('event.show', $e) }}" target="_blank">Stránka ↗</a></td>
    </tr>
  @empty
    <tr><td colspan="7" class="muted">Zatím žádné události. Založte ji příkazem <code>gratulomat:event</code>.</td></tr>
  @endforelse
  </tbody>
</table></div>
