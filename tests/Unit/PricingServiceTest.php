<?php

namespace Tests\Unit;

use App\Domain\Pricing\EventConfig;
use App\Domain\Pricing\PricingService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private function service(array $override = []): PricingService
    {
        $config = require __DIR__.'/../../config/gratulomat.php';

        return new PricingService(array_replace_recursive($config, $override));
    }

    public static function guestCases(): array
    {
        return [
            '5 hostů = Ochutnávka zdarma' => [5, 'ochutnavka', 0, 5],
            '6 hostů = Párty' => [6, 'party', 990, 25],
            '25 hostů = Párty' => [25, 'party', 990, 25],
            '26 hostů = Párty + 1 balíček' => [26, 'party', 1280, 45],
            '80 hostů = Párty + 3 balíčky' => [80, 'party', 1860, 85],
            '90 hostů = Premiéra je levnější' => [90, 'premiera', 1990, 150],
            '150 hostů = Premiéra' => [150, 'premiera', 1990, 150],
            '151 hostů = Premiéra + 1 balíček' => [151, 'premiera', 2280, 170],
        ];
    }

    #[DataProvider('guestCases')]
    public function test_guest_counts(int $guests, string $plan, int $total, int $limit): void
    {
        $q = $this->service()->quote(new EventConfig(guests: $guests));

        $this->assertSame($plan, $q->plan);
        $this->assertSame($total, (int) $q->totalCzk());
        $this->assertSame($limit, $q->guestLimit);
        $this->assertGreaterThanOrEqual($guests, $q->guestLimit);
    }

    public function test_feature_raises_plan(): void
    {
        $q = $this->service()->quote(new EventConfig(guests: 10, features: ['videos']));
        $this->assertSame('premiera', $q->plan);
        $this->assertNotEmpty($q->reasons);
    }

    public function test_collect_days_raise_plan(): void
    {
        $this->assertSame('party', $this->service()->quote(new EventConfig(collectDays: 60))->plan);
        $this->assertSame('premiera', $this->service()->quote(new EventConfig(collectDays: 120))->plan);
    }

    public function test_addons_move_ochutnavka_to_party(): void
    {
        $q = $this->service()->quote(new EventConfig(photobooks: 1));
        $this->assertSame('party', $q->plan);
        $this->assertSame(2280, (int) $q->totalCzk());
    }

    public function test_vip_services_become_inquiry(): void
    {
        $q = $this->service()->quote(new EventConfig(features: ['vip_services']));
        $this->assertTrue($q->isInquiry);
        $this->assertSame('vip', $q->plan);
    }

    public function test_vat_is_split_per_line_to_halere(): void
    {
        $q = $this->service()->quote(new EventConfig(guests: 10));
        $this->assertSame(81818, $q->baseHalere);
        $this->assertSame(17182, $q->vatHalere);
        $this->assertSame($q->totalHalere, $q->baseHalere + $q->vatHalere);
    }

    public function test_pack_limit_pushes_to_next_plan(): void
    {
        $q = $this->service(['addons' => ['guest_pack' => ['max_per_event' => 2]]])->quote(new EventConfig(guests: 80));
        $this->assertSame('premiera', $q->plan);
    }

    public function test_funeral_has_no_game(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->quote(new EventConfig(eventType: 'smutecni', guests: 30, features: ['game']));
    }

    public function test_unknown_feature_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->quote(new EventConfig(features: ['teleport']));
    }
}
