<?php

namespace Tests\Feature;

use App\Domain\Guest\MemoryPresenter;
use App\Http\Controllers\Backoffice\MusicController;
use App\Models\Event;
use App\Models\MusicTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class MusicLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gratulomat.operators' => ['op@gratulomat.cz']]);
    }

    protected function tearDown(): void
    {
        foreach (MusicTrack::all() as $t) {
            @unlink($t->path());
        }
        parent::tearDown();
    }

    private function track(array $attrs = []): MusicTrack
    {
        File::ensureDirectoryExists(storage_path('app/music'));
        $file = strtolower(Str::random(32)).'.mp3';
        file_put_contents(storage_path('app/music/'.$file), str_repeat('ID3', 100));

        return MusicTrack::create(['title' => 'Slunečné ráno', 'tags' => ['veselá', 'akustická'], 'file' => $file, 'bytes' => 300, 'duration' => 125, ...$attrs]);
    }

    private function event(): Event
    {
        $e = Event::create(['slug' => 'janinych-60', 'title' => 'Janiných 60', 'recipient_name' => 'Jana', 'event_date' => now()->addMonth(),
            'collect_until' => now()->addWeeks(3), 'plan' => 'party']);
        $u = User::create(['email' => 'org@example.cz', 'name' => 'org']);
        $e->organizers()->attach($u->id, ['role' => 'owner']);

        return $e;
    }

    public function test_tags_are_normalized(): void
    {
        $this->assertSame(['veselá', 'piano'], MusicController::tags('Veselá, piano ;  Piano,,'));
    }

    public function test_operator_uploads_edits_and_deletes_track(): void
    {
        $this->get('/backoffice/hudba')->assertRedirect();
        $this->actingAs(User::create(['email' => 'op@gratulomat.cz', 'name' => 'op']));
        $this->get('/backoffice/hudba')->assertOk()->assertSee('Nahrát skladbu');

        $this->post('/backoffice/hudba', ['file' => UploadedFile::fake()->create('song.mp3', 200, 'audio/mpeg'), 'title' => 'Slavnostní fanfára',
            'license' => 'Pixabay Content License', 'tags' => 'Slavnostní, orchestr', 'duration' => 95])->assertRedirect('/backoffice/hudba');
        $t = MusicTrack::firstOrFail();
        $this->assertSame(['slavnostní', 'orchestr'], $t->tags);
        $this->assertSame(95, $t->duration);
        $this->assertFileExists($t->path());
        $this->get('/hudba/'.$t->file)->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->get('/backoffice/hudba')->assertSee('Slavnostní fanfára')->assertSee('1:35');

        $this->post('/backoffice/hudba', ['file' => UploadedFile::fake()->create('song.wav', 200, 'audio/wav'), 'title' => 'X'])->assertSessionHasErrors('file');

        $this->post("/backoffice/hudba/{$t->id}", ['title' => 'Fanfára', 'tags' => 'slavnostní'])->assertRedirect();
        $this->assertFalse($t->fresh()->active);
        $this->assertSame('Fanfára', $t->fresh()->title);

        $path = $t->path();
        $this->delete("/backoffice/hudba/{$t->id}")->assertRedirect();
        $this->assertFileDoesNotExist($path);
        $this->assertSame(0, MusicTrack::count());
    }

    public function test_organizer_picks_track_and_kino_plays_it(): void
    {
        $e = $this->event();
        $t = $this->track();
        $off = $this->track(['title' => 'Vypnutá', 'active' => false]);
        $this->actingAs(User::first());

        $state = $this->getJson('/janinych-60/admin/api/state')->assertOk()->json();
        $this->assertSame([$t->id], array_column($state['music']['tracks'], 'id'));
        $this->assertContains('klidná', $state['music']['recommended']);

        $this->postJson('/janinych-60/admin/api/show', ['music_track_id' => $off->id])->assertNotFound();
        $this->postJson('/janinych-60/admin/api/show', ['music_track_id' => $t->id])->assertOk();
        $this->getJson('/janinych-60/admin/api/state')->assertJsonPath('show.music_track_id', $t->id);

        $this->get('/janinych-60/kino')->assertOk()->assertSee('data-music src="'.$t->url().'"', false);
        $this->assertTrue(MemoryPresenter::show($e->fresh(), '/m')['music']);

        // vypnutá skladba se nehraje
        $t->update(['active' => false]);
        $this->get('/janinych-60/kino')->assertDontSee('data-music', false);

        $this->postJson('/janinych-60/admin/api/show', ['music_track_id' => null])->assertOk();
        $this->assertNull($e->fresh()->show->music_track_id);
    }

    public function test_funeral_recommends_calm_tags(): void
    {
        $e = $this->event();
        $e->update(['type' => 'smutecni']);
        $this->assertSame(['smuteční', 'klidná', 'dojemná'], MusicTrack::recommendedTags($e));
    }
}
