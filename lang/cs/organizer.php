<?php

// Výchozí texty pozvánek (v primárním jazyce události). V textu: {jmena}, {odkaz}, {datum} se doplní u každé pozvánky.
return [
    'invite' => [
        // bez skloňování jmen: název události se jen cituje
        'default' => "Ahoj {jmena}!\nK události „:title“ ({datum}) připravujeme malé překvapení – společný film ze vzpomínek, fotek a přání.\nMáte nějakou historku, fotku ze šuplíku nebo vzkaz? Nahrajte to prosím tady (odkaz je jen pro vás):\n{odkaz}\nDěkujeme!",
        'narozeniny' => "Ahoj {jmena}! 🎉\nK události „:title“ ({datum}) připravujeme malé překvapení – společný film ze vzpomínek, fotek a přání.\nMáte nějakou historku, fotku ze šuplíku nebo vzkaz? Nahrajte to prosím tady (odkaz je jen pro vás):\n{odkaz}\nDěkujeme a těšíme se!",
        'podekovani' => "Ahoj {jmena}!\nK události „:title“ ({datum}) chystáme společné poděkování – film ze vzpomínek, fotek a vzkazů.\nMáte nějakou historku, fotku nebo pár slov díků? Nahrajte to prosím tady (odkaz je jen pro vás):\n{odkaz}\nDěkujeme!",
        'smutecni' => "Dobrý den, {jmena},\nk rozloučení „:title“ sbíráme vzpomínky, fotografie a vzkazy pro rodinu a blízké. Pokud se chcete připojit, můžete tak učinit zde (odkaz je jen pro vás):\n{odkaz}\nDěkujeme.",
    ],
    // dřívější výchozí texty (se skloňováním) – uložené beze změny se nahradí novými
    'invite_v1' => [
        'default' => "Ahoj {jmena}!\nChystáme pro :acc malé překvapení – společný film ze vzpomínek, fotek a přání.\nMáte nějakou historku, fotku ze šuplíku nebo vzkaz? Nahrajte to prosím tady (odkaz je jen pro vás):\n{odkaz}\nDěkujeme!",
        'narozeniny' => "Ahoj {jmena}! 🎉\n{datum} slaví :name :age a chystáme pro něj malé překvapení – film z vašich vzpomínek.\nMáte nějakou historku, fotku ze šuplíku nebo vzkaz? Nahrajte to prosím tady (odkaz je jen pro vás):\n{odkaz}\nDěkujeme a těšíme se!",
        'smutecni' => "Dobrý den, {jmena},\nrádi bychom společně zavzpomínali na :acc. Pokud máte vzpomínku, fotografii nebo chcete napsat vzkaz rodině, můžete tak učinit zde (odkaz je jen pro vás):\n{odkaz}\nDěkujeme.",
    ],
];
