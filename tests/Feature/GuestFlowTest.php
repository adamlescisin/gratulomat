<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Household;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GuestFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir().'/gm-test-'.uniqid();
        File::ensureDirectoryExists($this->storage.'/app');
        $this->app->useStoragePath($this->storage);
        config(['gratulomat.site_mode' => 'coming_soon']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function event(array $attrs = []): Event
    {
        $event = Event::create(array_merge([
            'slug' => 'adamovych-50', 'type' => 'narozeniny', 'title' => 'Adamových 50',
            'recipient_name' => 'Adam', 'recipient_forms' => ['dat' => 'Adamovi', 'acc' => 'Adama', 'voc' => 'Adame', 'poss' => 'Adamových', 'ins' => 'Adamem', 'loc' => 'Adamovi', 'gen' => 'Adama'],
            'start_year' => 1977, 'event_date' => now()->addMonth(), 'collect_until' => now()->addMonth(),
            'status' => 'collecting', 'plan' => 'premiera', 'is_test' => true,
        ], $attrs));
        $event->show()->create([]);

        return $event;
    }

    private function household(Event $event, string $label = 'Petra a Honza'): Household
    {
        return $event->households()->create(['token' => Household::newToken(), 'label' => $label, 'names' => Household::splitNames($label)]);
    }

    private function loginAs(Household $h): static
    {
        $this->get("/{$h->event->slug}/g/{$h->token}")->assertRedirect("/{$h->event->slug}");

        return $this;
    }

    public function test_invite_link_logs_guest_in_and_shows_form(): void
    {
        $event = $this->event(['event_date' => '2027-01-23 18:00', 'collect_until' => '2027-01-23 18:00']);
        $h = $this->household($event);

        $this->get('/adamovych-50')->assertOk()->assertSee(__('guest.landing_lead'));
        $this->loginAs($h)->get('/adamovych-50')->assertOk()
            ->assertSee('Adamových 50 let', false)
            ->assertSee('Přání pro Adama');

        $me = $this->getJson('/adamovych-50/api/me')->assertOk()->json();
        $this->assertSame(['Petra', 'Honza'], $me['household']['names']);
        $this->assertSame(200, $me['config']['max_video_mb']);
        $this->assertNull($me['wall'], 'Příspěvky ostatních jsou do oslavy zapečetěné.');
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->event();
        $this->get('/adamovych-50/g/neplatny99')->assertRedirect('/adamovych-50?neplatny=1');
        $this->getJson('/adamovych-50/api/me')->assertUnauthorized();
    }

    public function test_guest_saves_memory_with_photo_and_media_is_protected(): void
    {
        $event = $this->event();
        $h = $this->household($event);
        $this->loginAs($h);

        $res = $this->post('/adamovych-50/api/memories', [
            'title' => 'Šumava 98', 'body' => 'Zapadli jsme.', 'year' => 1998, 'tags' => ['Cesty', 'Neexistuje'],
            'authors' => ['Petra'], 'files' => [UploadedFile::fake()->image('a.jpg', 3000, 2000)],
        ], ['Accept' => 'application/json'])->assertOk()->json();

        $this->assertSame(['Cesty'], $res['tags']);
        $this->assertSame(['Petra'], $res['authors']);
        $this->assertSame(2560, $res['media'][0]['width']);
        $dir = $event->mediaDir($res['id']);
        $this->assertFileExists("$dir/memory.json");
        $this->assertFileExists($dir.'/'.$res['media'][0]['thumb']);

        $this->get("/adamovych-50/m/{$res['id']}/{$res['media'][0]['file']}")->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // jiný host před oslavou nic nevidí
        $this->loginAs($this->household($event, 'Marek'));
        $this->get("/adamovych-50/m/{$res['id']}/{$res['media'][0]['file']}")->assertNotFound();
        $this->postJson("/adamovych-50/api/memories/{$res['id']}", ['body' => 'cizí'])->assertNotFound();
    }

    public function test_private_wish_is_hidden_from_other_guests_after_event(): void
    {
        $event = $this->event(['event_date' => now()->subDay(), 'collect_until' => now()->addDay()]);
        $author = $this->household($event);
        $this->loginAs($author);
        $saved = $this->postJson('/adamovych-50/api/memories', ['body' => 'Příběh', 'wish_text' => 'Tajné přání'])->assertOk()->json();
        $this->assertFalse($saved['wish_public']);

        $this->loginAs($this->household($event, 'Marek'));
        $wall = $this->getJson('/adamovych-50/api/me')->json('wall');
        $this->assertCount(1, $wall);
        $this->assertNull($wall[0]['wish_text']);
        $this->assertSame('Příběh', $wall[0]['body']);
    }

    public function test_guest_deletes_own_memory_and_files(): void
    {
        $event = $this->event();
        $this->loginAs($this->household($event));
        $id = $this->post('/adamovych-50/api/memories', ['files' => [UploadedFile::fake()->image('b.png', 400, 300)]], ['Accept' => 'application/json'])->json('id');
        $dir = $event->mediaDir($id);

        $this->deleteJson("/adamovych-50/api/memories/$id")->assertOk();
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertSame(0, $event->memories()->count());
    }

    public function test_empty_memory_and_closed_collection_are_rejected(): void
    {
        $event = $this->event();
        $this->loginAs($this->household($event));
        $this->postJson('/adamovych-50/api/memories', ['title' => ' '])->assertStatus(422);

        $event->update(['collect_until' => now()->subMinute()]);
        $this->postJson('/adamovych-50/api/memories', ['body' => 'pozdě'])->assertForbidden();
    }

    public function test_ochutnavka_has_no_wishes_no_videos_and_photo_limit(): void
    {
        $event = $this->event(['plan' => 'ochutnavka', 'limits' => ['photos' => 1]]);
        $this->loginAs($this->household($event));

        $this->get('/adamovych-50')->assertDontSee('Přání pro Adama');
        $saved = $this->postJson('/adamovych-50/api/memories', ['body' => 'Ahoj', 'wish_text' => 'nepůjde', 'wish_public' => 1])->json();
        $this->assertNull($saved['wish_text']);

        $this->post('/adamovych-50/api/memories', ['files' => [UploadedFile::fake()->image('1.jpg')]], ['Accept' => 'application/json'])->assertOk();
        $this->post('/adamovych-50/api/memories', ['files' => [UploadedFile::fake()->image('2.jpg')]], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_kino_opens_only_when_organiser_makes_it_public(): void
    {
        $event = $this->event(['event_date' => now()->subHour()]);
        $this->loginAs($this->household($event));
        $this->postJson('/adamovych-50/api/memories', ['body' => 'Vzpomínka', 'wish_text' => 'Veřejné', 'wish_public' => 1]);

        $this->get('/adamovych-50/kino')->assertForbidden();
        $this->getJson('/adamovych-50/api/show')->assertForbidden();

        $event->show->update(['public_from' => now()->subMinute()]);
        $this->get('/adamovych-50/kino')->assertOk()->assertSee('Spustit film');
        $show = $this->getJson('/adamovych-50/api/show')->assertOk()->json();
        $this->assertSame(now()->year - 1977, $show['age']);
        $this->assertSame('Adamovi', $show['celebrant_dative']);
        $this->assertSame('Veřejné', $show['memories'][0]['wish_text']);
    }

    public function test_coming_soon_hides_non_test_draft_events_only(): void
    {
        $this->event(['slug' => 'draft-akce', 'is_test' => false, 'status' => 'draft']);
        $this->event(['slug' => 'test-akce', 'is_test' => true]);

        $this->get('/draft-akce')->assertSee('Mám přístupový kód');
        $this->get('/test-akce')->assertSee(__('guest.landing_lead'));
    }

    public function test_guest_language_choice_is_saved_to_household(): void
    {
        $event = $this->event();
        $h = $this->household($event);
        $this->loginAs($h);

        $this->post('/jazyk/de');
        $this->assertSame('de', $h->fresh()->preferred_locale);
        $this->get('/adamovych-50')->assertSee('<html lang="de">', false)->assertSee('Erinnerung speichern');
    }

    public function test_funeral_texts_and_wall_open_immediately(): void
    {
        $event = $this->event(['slug' => 'vzpominka-jana', 'type' => 'smutecni', 'title' => 'Vzpomínka na Jana', 'recipient_name' => 'Jan', 'recipient_forms' => ['acc' => 'Jana']]);
        $this->loginAs($this->household($event));
        $this->get('/vzpominka-jana')->assertSee('zavzpomínejte s námi na', false)->assertSee('Vzkaz pro rodinu');
        $this->assertSame([], $this->getJson('/vzpominka-jana/api/me')->json('wall'));
    }
}
