<?php

namespace Tests\Feature;

use App\Mail\LoginLink;
use App\Models\Event;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use ZipArchive;

class OrganizerAdminTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir().'/gm-org-'.uniqid();
        File::ensureDirectoryExists($this->storage.'/app');
        $this->app->useStoragePath($this->storage);
        config(['gratulomat.site_mode' => 'coming_soon']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function setupEvent(array $attrs = []): array
    {
        $event = Event::create(array_merge([
            'slug' => 'adamovych-50', 'type' => 'narozeniny', 'title' => 'Adamových 50', 'recipient_name' => 'Adam',
            'recipient_forms' => ['acc' => 'Adama', 'dat' => 'Adamovi'], 'start_year' => 1977,
            'event_date' => now()->addMonth(), 'collect_until' => now()->addMonth(), 'status' => 'collecting',
            'plan' => 'premiera', 'limits' => ['guests' => 3], 'is_test' => true,
        ], $attrs));
        $event->show()->create([]);
        $user = User::create(['email' => 'adam@example.com', 'name' => 'Adam']);
        $event->organizers()->attach($user, ['role' => 'owner']);

        return [$event, $user];
    }

    public function test_magic_link_login_is_one_time(): void
    {
        Mail::fake();
        [$event, $user] = $this->setupEvent();

        $this->post('/prihlaseni', ['email' => 'ADAM@example.com'])->assertRedirect('/prihlaseni');
        $this->post('/prihlaseni', ['email' => 'nikdo@example.com'])->assertRedirect('/prihlaseni');
        Mail::assertSent(LoginLink::class, 1);

        $link = $user->loginLink();
        $this->get($link)->assertRedirect('/adamovych-50/admin');
        $this->assertAuthenticatedAs($user);
        $this->get('/adamovych-50/admin')->assertOk()->assertSee('Videoprezentace pro hosty');

        auth()->logout();
        $this->get($link)->assertRedirect('/prihlaseni?vyprsel=1');
        $this->assertGuest();
    }

    public function test_admin_is_only_for_organizers_of_that_event(): void
    {
        [$event] = $this->setupEvent();
        $this->get('/adamovych-50/admin')->assertRedirect('/prihlaseni');
        $stranger = User::create(['email' => 'cizi@example.com', 'name' => 'Cizí']);
        $this->actingAs($stranger)->get('/adamovych-50/admin')->assertNotFound();
        $this->actingAs($stranger)->getJson('/adamovych-50/admin/api/state')->assertNotFound();
    }

    public function test_organizer_manages_households_within_plan_limit(): void
    {
        [$event, $user] = $this->setupEvent();
        $this->actingAs($user);

        $r = $this->postJson('/adamovych-50/admin/api/households', ['lines' => "Petra a Honza; spolužáci\nMarek\n\nJana\nNavíc"])->assertOk()->json();
        $this->assertSame(['added' => 3, 'skipped' => 1, 'limit' => 3], $r);

        $state = $this->getJson('/adamovych-50/admin/api/state')->assertOk()->json();
        $this->assertCount(3, $state['households']);
        $this->assertStringContainsString('{odkaz}', $state['event']['invite_text']);
        $this->assertStringContainsString('Adam', $state['event']['invite_text']);

        $h = Household::where('label', 'Marek')->first();
        $old = $h->token;
        $this->postJson("/adamovych-50/admin/api/households/{$h->id}", ['new_token' => true])->assertOk();
        $this->assertNotSame($old, $h->fresh()->token);

        $this->postJson("/adamovych-50/admin/api/households/{$h->id}", ['label' => 'Marek a Eva', 'relation' => 'sousedé'])->assertOk();
        $this->assertSame(['Marek', 'Eva'], $h->fresh()->names);

        $this->postJson("/adamovych-50/admin/api/households/{$h->id}", ['delete' => true])->assertOk();
        $this->assertSame(2, $event->households()->count());
    }

    public function test_organizer_moderates_and_sees_private_wishes(): void
    {
        [$event, $user] = $this->setupEvent();
        $h = $event->households()->create(['token' => Household::newToken(), 'label' => 'Petra', 'names' => ['Petra']]);
        $m = $event->memories()->create(['household_id' => $h->id, 'authors' => ['Petra'], 'tags' => [], 'body' => 'Příběh', 'wish_text' => 'Tajné', 'wish_public' => false]);

        $this->actingAs($user);
        $state = $this->getJson('/adamovych-50/admin/api/state')->json();
        $this->assertSame('Tajné', $state['memories'][0]['wish_text']);

        $this->postJson("/adamovych-50/admin/api/memories/{$m->id}", ['status' => 'hidden'])->assertOk();
        $this->assertSame('hidden', $m->fresh()->status);
        $this->postJson("/adamovych-50/admin/api/memories/{$m->id}", ['delete' => true])->assertOk();
        $this->assertSame(0, $event->memories()->count());
    }

    public function test_organizer_opens_schedules_and_closes_show(): void
    {
        [$event, $user] = $this->setupEvent();
        $h = $event->households()->create(['token' => Household::newToken(), 'label' => 'Petra', 'names' => ['Petra']]);

        // organizátor vidí náhled vždy
        $this->actingAs($user)->get('/adamovych-50/kino')->assertOk();

        $this->postJson('/adamovych-50/admin/api/show', ['public_from' => now()->addDay()->format('Y-m-d\TH:i')])->assertOk();
        $this->assertFalse($event->fresh()->showPublic());
        $this->postJson('/adamovych-50/admin/api/show', ['public_from' => 'now', 'game_enabled' => true])->assertOk();
        $this->assertTrue($event->fresh()->showPublic());
        $this->assertTrue($event->fresh()->show->game_enabled);

        auth()->logout();
        $this->get("/adamovych-50/g/{$h->token}");
        $this->get('/adamovych-50/kino')->assertOk();

        $this->actingAs($user)->postJson('/adamovych-50/admin/api/show', ['public_from' => null])->assertOk();
        $this->assertFalse($event->fresh()->showPublic());
    }

    public function test_offline_package_contains_player_data_and_media(): void
    {
        [$event, $user] = $this->setupEvent();
        $h = $event->households()->create(['token' => Household::newToken(), 'label' => 'Petra', 'names' => ['Petra']]);
        $m = $event->memories()->create(['household_id' => $h->id, 'authors' => ['Petra'], 'tags' => [], 'body' => 'Veřejné', 'wish_text' => 'Soukromé', 'wish_public' => false]);
        $m->setRelation('event', $event);
        file_put_contents($m->dir().'/00-x.jpg', 'jpg');
        $m->media()->create(['kind' => 'photo', 'file' => '00-x.jpg', 'mime' => 'image/jpeg', 'bytes' => 3]);

        $res = $this->actingAs($user)->get('/adamovych-50/admin/offline.zip')->assertOk();
        $zip = new ZipArchive;
        $zip->open($res->baseResponse->getFile()->getPathname());
        $this->assertNotFalse($zip->locateName('index.html'));
        $this->assertNotFalse($zip->locateName('assets/js/kino.js'));
        $this->assertNotFalse($zip->locateName("media/{$m->id}/00-x.jpg"));
        $index = $zip->getFromName('index.html');
        $this->assertStringContainsString('"demoShow"', $index);
        $this->assertStringNotContainsString('Soukromé', $index, 'Soukromé přání se nepromítá.');
        $this->assertStringContainsString('Soukromé', $zip->getFromName('private-wishes.json'));
        $zip->close();
    }

    public function test_offline_package_needs_party_plan(): void
    {
        [$event, $user] = $this->setupEvent(['plan' => 'ochutnavka']);
        $this->actingAs($user)->get('/adamovych-50/admin/offline.zip')->assertForbidden();
    }

    public function test_organizer_command_prints_login_link(): void
    {
        [$event] = $this->setupEvent();
        $this->artisan('gratulomat:organizer', ['slug' => 'adamovych-50', 'email' => 'jana@example.com'])
            ->expectsOutputToContain('/prihlaseni/odkaz/')->assertSuccessful();
        $this->assertTrue(User::where('email', 'jana@example.com')->first()->organizes($event));
    }
}
