<footer class="cs-wrap cs-foot">
  <span>© {{ date('Y') }} Gratulomat · gratulomat.cz @if ($legalId = \App\Support\Legal::identityLine())<br><small>Provozovatel: {{ $legalId }}</small>@endif</span>
  <nav><a href="{{ route('terms') }}">Obchodní podmínky</a><a href="{{ route('guest-rules') }}">Pravidla pro hosty</a><a href="{{ route('privacy') }}">Ochrana soukromí</a>@if (config('gratulomat.ga_id'))<a href="#" onclick="gmCookieSettings();return false">Nastavení cookies</a>@endif<a href="mailto:{{ \App\Support\Legal::identity()['email'] }}">Kontakt</a></nav>
</footer>
