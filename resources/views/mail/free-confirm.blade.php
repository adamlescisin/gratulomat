<x-mail::message>
# Ještě jeden klik

Potvrďte prosím založení události **{{ $order->config['name'] }}** v tarifu Ochutnávka. Po potvrzení se stránka hned vytvoří a pošleme vám odkaz do administrace.

<x-mail::button :url="$link">
Potvrdit a založit událost
</x-mail::button>

Odkaz platí 24 hodin. Pokud jste událost nezakládali, e-mail ignorujte.

Gratulomat
</x-mail::message>
