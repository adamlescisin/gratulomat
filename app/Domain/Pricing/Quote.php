<?php

namespace App\Domain\Pricing;

/**
 * Výsledek výpočtu: tarif, položky, celkem s DPH, základ a daň, důvody.
 * Částky v haléřích (int), aby se nezaokrouhlovalo na floatech.
 */
final class Quote
{
    /**
     * @param  list<array{code:string,name:string,qty:int,unit_price:int,total:int}>  $lines
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly string $plan,
        public readonly array $lines,
        public readonly int $totalHalere,
        public readonly int $baseHalere,
        public readonly int $vatHalere,
        public readonly int $guestLimit,
        public readonly array $reasons,
        public readonly bool $isInquiry = false,
    ) {}

    public function totalCzk(): float
    {
        return $this->totalHalere / 100;
    }

    public function toArray(): array
    {
        return [
            'plan' => $this->plan,
            'lines' => $this->lines,
            'total' => $this->totalHalere / 100,
            'base' => $this->baseHalere / 100,
            'vat' => $this->vatHalere / 100,
            'guest_limit' => $this->guestLimit,
            'reasons' => $this->reasons,
            'inquiry' => $this->isInquiry,
        ];
    }
}
