<?php

namespace App\Domain\Pricing;

/**
 * Konfigurace události z průvodce – vstup pro výpočet ceny.
 */
final class EventConfig
{
    /**
     * @param  list<string>  $features  klíče z config('gratulomat.features')
     */
    public function __construct(
        public readonly string $eventType = 'narozeniny',
        public readonly int $guests = 5,
        public readonly array $features = [],
        public readonly int $collectDays = 14,
        public readonly int $extraArchiveYears = 0,
        public readonly int $photobooks = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            eventType: (string) ($data['event_type'] ?? 'narozeniny'),
            guests: max(1, (int) ($data['guests'] ?? 5)),
            features: array_values(array_unique(array_map('strval', $data['features'] ?? []))),
            collectDays: max(1, (int) ($data['collect_days'] ?? 14)),
            extraArchiveYears: max(0, (int) ($data['extra_archive_years'] ?? 0)),
            photobooks: max(0, (int) ($data['photobooks'] ?? 0)),
        );
    }
}
