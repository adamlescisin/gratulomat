<x-mail::message>
# Archiv brzy končí

Stránka a videoprezentace události **{{ $event->title }}** budou dostupné do **{{ $event->archive_until->locale('cs')->isoFormat('LL') }}**. Potom je hosté neuvidí a po dalších {{ \App\Models\Event::PURGE_GRACE_DAYS }} dnech fotky, videa a vzkazy natrvalo smažeme.

Než se to stane, můžete:
- archiv prodloužit v administraci (Tarif a doplňky → Archiv +1 rok),
- stáhnout si offline balíček videoprezentace na památku.

<x-mail::button :url="route('organizer.admin', $event)">
Otevřít administraci
</x-mail::button>

Gratulomat
</x-mail::message>
