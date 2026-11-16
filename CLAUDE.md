# Gratulomat – pokyny pro práci v repozitáři

- Zdroj pravdy je specifikace (odkaz v README). Při změně chování ji drž v souladu, nebo na rozdíl upozorni.
- Uživatelské texty česky; v kódu angličtina pro identifikátory, komentáře česky. Uživatelské řetězce vždy přes `__()` a klíč doplnit do `lang/cs.json`, `en.json`, `de.json` (test hlídá shodu klíčů).
- Terminologie: tarif „Premiéra“ ≠ „videoprezentace“; tarify Ochutnávka, Párty, Premiéra, VIP; „Událost“, ne „Oslava“; u smutečního rozloučení „host“ a „vzkaz“.
- Ceny: výpočet jen na serveru (`PricingService`), částky v haléřích, ceny včetně DPH 21 %.
- Doklady vystavuje worker na Mac Mini v MyÚčtu; Hostinger s MyÚčtem nikdy nemluví přímo.
- Před commitem: `vendor/bin/pint` a `vendor/bin/phpunit`.
