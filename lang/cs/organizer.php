<?php

// Výchozí texty pozvánek (v primárním jazyce události). V textu: {jmena}, {odkaz}, {datum} se doplní u každé pozvánky.
return [
    'invite' => [
        'default' => "Ahoj {jmena}!\nChystáme pro :acc malé překvapení – společný film ze vzpomínek, fotek a přání.\nMáte nějakou historku, fotku ze šuplíku nebo vzkaz? Nahrajte to prosím tady (odkaz je jen pro vás):\n{odkaz}\nDěkujeme!",
        'narozeniny' => "Ahoj {jmena}! 🎉\n{datum} slaví :name :age a chystáme pro něj malé překvapení – film z vašich vzpomínek.\nMáte nějakou historku, fotku ze šuplíku nebo vzkaz? Nahrajte to prosím tady (odkaz je jen pro vás):\n{odkaz}\nDěkujeme a těšíme se!",
        'smutecni' => "Dobrý den, {jmena},\nrádi bychom společně zavzpomínali na :acc. Pokud máte vzpomínku, fotografii nebo chcete napsat vzkaz rodině, můžete tak učinit zde (odkaz je jen pro vás):\n{odkaz}\nDěkujeme.",
    ],
];
