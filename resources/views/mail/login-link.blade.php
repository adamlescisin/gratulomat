<x-mail::message>
# Přihlášení do Gratulomatu

Dobrý den{{ $user->name ? ', '.$user->name : '' }},

pro přihlášení do administrace své události klikněte na tlačítko. Odkaz platí 30 minut a dá se použít jen jednou.

<x-mail::button :url="$link">
Přihlásit se
</x-mail::button>

Pokud jste o přihlášení nežádali, e-mail můžete ignorovat.

Gratulomat
</x-mail::message>
