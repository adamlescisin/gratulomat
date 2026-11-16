<?php

namespace App\Domain\Orders;

/**
 * Hrubé skloňování křestního jména pro texty na stránce hosta (dat, acc, voc, gen, loc, ins, poss).
 * Pokrývá běžná jména; organizátor tvary může upravit v administraci. U párů, týmů a firem se neskloňuje.
 */
class CzechNames
{
    public static function forms(string $name, string $recipientType): array
    {
        $name = trim($name);
        if ($recipientType !== 'person' || $name === '' || str_contains($name, ' ')) {
            return [];
        }
        $lower = mb_strtolower($name);
        $stem = mb_substr($name, 0, -1);
        $last = mb_substr($lower, -1);

        // ženská jména na -a (Jana, Lenka, Petra)
        if ($last === 'a') {
            $softStem = preg_replace(['/k$/u', '/h$/u', '/g$/u', '/r$/u', '/ch$/u'], ['c', 'z', 'z', 'ř', 'š'], $stem);
            $possStem = preg_replace(['/k$/u', '/h$/u', '/r$/u'], ['č', 'ž', 'ř'], $stem);

            return [
                'gen' => $stem.(preg_match('/[jcčřšžď]$/u', $stem) ? 'e' : 'y'),
                'dat' => $dat = $softStem.(preg_match('/[cřzš]$/u', $softStem) && $softStem !== $stem ? 'e' : (preg_match('/[jčšž]$/u', $softStem) ? 'i' : 'ě')),
                'acc' => $stem.'u',
                'voc' => $stem.'o',
                'loc' => $dat,
                'ins' => $stem.'ou',
                'poss' => $possStem.'iných',
            ];
        }
        // ženská jména na -e/-ie (Marie, Lucie)
        if ($last === 'e') {
            return ['gen' => $name, 'dat' => $stem.'i', 'acc' => $stem.'i', 'voc' => $name, 'loc' => $stem.'i', 'ins' => $stem.'í', 'poss' => $stem.'iných'];
        }
        // mužská jména zakončená souhláskou (Adam, Petr, Tomáš, Pavel)
        if (preg_match('/[bcčdďfghjklmnňpqrřsštťvwxzž]$/u', $lower)) {
            $base = preg_replace('/el$/u', 'l', $name);               // Pavel → Pavl-
            $soft = preg_match('/[jcčřšžť]$/u', mb_strtolower($base)) || preg_match('/(ek|ec)$/u', $lower);
            if (preg_match('/ek$/u', $lower)) {                      // Marek → Mark-
                $base = mb_substr($name, 0, -2).'k';
                $soft = false;
            }
            $voc = match (true) {
                (bool) preg_match('/r$/u', $lower) && ! preg_match('/[aeiouy]r$/u', $lower) => mb_substr($base, 0, -1).'ře',
                (bool) preg_match('/k$/u', mb_strtolower($base)) => $base.'u',
                (bool) preg_match('/(h|ch|g)$/u', mb_strtolower($base)) => $base.'u',
                $soft => $base.'i',
                default => $base.'e',
            };

            return [
                'gen' => $base.($soft ? 'e' : 'a'),
                'dat' => $base.'ovi',
                'acc' => $base.($soft ? 'e' : 'a'),
                'voc' => $voc,
                'loc' => $base.'ovi',
                'ins' => $base.'em',
                'poss' => $base.'ových',
            ];
        }

        return [];
    }
}
