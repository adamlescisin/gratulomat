<x-mail::message>
# Nový zájemce na čekací listině

**{{ $entry->email }}** se přihlásil(a) k upozornění na spuštění Gratulomatu.

- Čas: {{ $entry->created_at->timezone('Europe/Prague')->format('j. n. Y H:i') }}
- Souhlas: ano (verze textu na coming soon stránce)
- Celkem na listině: {{ $total }}

<x-mail::button :url="url('/backoffice/cekaci-listina')">
Otevřít čekací listinu
</x-mail::button>
</x-mail::message>
