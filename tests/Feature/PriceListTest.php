<?php

namespace Tests\Feature;

use App\Domain\Orders\UpgradeService;
use App\Domain\Pricing\PriceList;
use App\Models\DiscountCode;
use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PriceListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gratulomat.site_mode' => 'coming_soon', 'gratulomat.tester_codes' => ['BETA-1234'], 'gratulomat.operators' => ['op@gratulomat.cz']]);
        $this->withCookie('gm_tester', 'BETA-1234');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function operator(): User
    {
        return User::create(['email' => 'op@gratulomat.cz', 'name' => 'op']);
    }

    private function prices(array $override = []): array
    {
        $base = [
            'ochutnavka' => ['price' => 0, 'sale_percent' => '', 'sale_until' => ''],
            'party' => ['price' => 990, 'sale_percent' => '', 'sale_until' => ''],
            'premiera' => ['price' => 1990, 'sale_percent' => '', 'sale_until' => ''],
            'vip' => ['price' => 4990, 'sale_percent' => '', 'sale_until' => ''],
        ];

        return ['plans' => array_replace_recursive($base, $override)];
    }

    private function quoteConfig(array $o = []): array
    {
        return array_merge([
            'name' => 'Petrových 40', 'slug' => 'petrovych-40', 'occasion' => 'birthday', 'date' => now()->addMonth()->toDateString(),
            'recipient' => 'person', 'recName' => 'Petr', 'guests' => 20, 'voice' => true, 'collect' => 14, 'archive' => 1,
        ], $o);
    }

    public function test_defaults_come_from_config(): void
    {
        $rows = PriceList::rows();
        $this->assertSame(990, $rows['party']['price']);
        $this->assertFalse($rows['party']['sale_active']);
    }

    public function test_sale_is_active_until_end_of_day_prague(): void
    {
        Setting::put(PriceList::SETTING, ['party' => ['price' => 1000, 'sale_percent' => 25, 'sale_until' => '2026-12-31']]);

        // 31. 12. 23:30 v Praze = 22:30 UTC → ještě platí
        $r = PriceList::rows(Carbon::parse('2026-12-31 22:30', 'UTC'))['party'];
        $this->assertTrue($r['sale_active']);
        $this->assertSame(750, $r['price']);
        $this->assertSame(1000, $r['list_price']);

        // 1. 1. 0:30 v Praze = 23:30 UTC 31. 12. → už neplatí
        $r = PriceList::rows(Carbon::parse('2026-12-31 23:30', 'UTC'))['party'];
        $this->assertFalse($r['sale_active']);
        $this->assertSame(1000, $r['price']);
    }

    public function test_discounted_price_rounds_to_whole_crowns(): void
    {
        $this->assertSame(663, PriceList::discounted(990, 33)); // 663,3
        $this->assertSame(1393, PriceList::discounted(1990, 30));
    }

    public function test_operator_saves_price_list_and_history(): void
    {
        $this->get('/backoffice/cenik')->assertRedirect();
        $this->actingAs($this->operator());
        $this->get('/backoffice/cenik')->assertOk()->assertSee('Základní cena')->assertSee('Premiéra');

        $until = now()->addWeek()->toDateString();
        $this->post('/backoffice/cenik', $this->prices(['party' => ['price' => 1090, 'sale_percent' => 20, 'sale_until' => $until]]))
            ->assertRedirect('/backoffice/cenik');

        $this->assertSame(872, config('gratulomat.plans.party.price'));
        $this->assertSame(1090, config('gratulomat.plans.party.list_price'));
        $history = Setting::get(PriceList::HISTORY);
        $this->assertCount(1, $history);
        $this->assertStringContainsString('Párty: 990 Kč → 1 090 Kč (−20 %', $history[0]['changes'][0]);
        $this->get('/backoffice/cenik')->assertSee('872 Kč')->assertSee('Historie změn');

        // slevu zrušíte smazáním procent
        $this->post('/backoffice/cenik', $this->prices(['party' => ['price' => 1090]]));
        $this->assertSame(1090, config('gratulomat.plans.party.price'));
        $this->assertNull(Setting::get(PriceList::SETTING)['party']['sale_until']);
    }

    public function test_validation(): void
    {
        $this->actingAs($this->operator());
        $this->post('/backoffice/cenik', $this->prices(['party' => ['sale_percent' => 20]]))->assertSessionHasErrors('plans');
        $this->post('/backoffice/cenik', $this->prices(['party' => ['sale_percent' => 95, 'sale_until' => now()->addDay()->toDateString()]]))->assertSessionHasErrors('plans.party.sale_percent');
        $this->post('/backoffice/cenik', $this->prices(['party' => ['sale_percent' => 10, 'sale_until' => now()->subDays(2)->toDateString()]]))->assertSessionHasErrors('plans.party.sale_until');
        $this->post('/backoffice/cenik', $this->prices(['premiera' => ['price' => 500]]))->assertSessionHasErrors('plans');
        $this->post('/backoffice/cenik', $this->prices(['party' => ['price' => -1]]))->assertSessionHasErrors('plans.party.price');
        $this->assertNull(Setting::get(PriceList::SETTING));
    }

    public function test_landing_and_quote_use_sale_price(): void
    {
        Setting::put(PriceList::SETTING, ['party' => ['price' => 990, 'sale_percent' => 20, 'sale_until' => now()->addWeek()->toDateString()]]);
        PriceList::apply();

        $this->get('/')->assertOk()->assertSee('<s>990 Kč</s>', false)->assertSee('−20 % do')->assertSee('792 Kč');

        $q = $this->postJson('/api/quote', ['config' => $this->quoteConfig()])->assertOk()->json();
        $this->assertSame(792, $q['total']);
        $this->assertSame('Tarif Párty (akce −20 %)', $q['lines'][0]['name']);
        $this->assertSame(99000, $q['lines'][0]['list_unit_price']);

        // slevový kód se počítá z akční ceny
        DiscountCode::create(['code' => 'JARO10', 'percent' => 10]);
        $q = $this->postJson('/api/quote', ['config' => $this->quoteConfig(['discount' => 'JARO10'])])->json();
        $this->assertSame(713, $q['total']);
    }

    public function test_expired_sale_is_not_shown(): void
    {
        Setting::put(PriceList::SETTING, ['party' => ['price' => 990, 'sale_percent' => 20, 'sale_until' => now()->subDay()->toDateString()]]);
        PriceList::apply();
        $this->get('/')->assertOk()->assertDontSee('<s>990 Kč</s>', false)->assertDontSee('−20 % do');
        $this->assertSame(990, $this->postJson('/api/quote', ['config' => $this->quoteConfig()])->json('total'));
    }

    public function test_upgrade_difference_never_negative(): void
    {
        // Premiéra v akci levnější než zaplacená Párty
        Setting::put(PriceList::SETTING, ['premiera' => ['price' => 1990, 'sale_percent' => 60, 'sale_until' => now()->addWeek()->toDateString()]]);
        PriceList::apply();
        $e = Event::create(['slug' => 'janinych-60', 'title' => 'Janiných 60', 'recipient_name' => 'Jana', 'event_date' => now()->addMonth(),
            'collect_until' => now()->addWeeks(3), 'archive_until' => now()->addMonths(2)->toDateString(), 'plan' => 'party',
            'limits' => ['guests' => 25, 'photos' => null]]);

        $q = app(UpgradeService::class)->quote($e, ['plan' => 'premiera']);
        $this->assertSame(0, $q['lines'][0]['total']);
    }
}
