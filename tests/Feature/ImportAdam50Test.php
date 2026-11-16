<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class ImportAdam50Test extends TestCase
{
    use RefreshDatabase;

    public function test_imports_prototype_data_and_keeps_tokens(): void
    {
        $tmp = sys_get_temp_dir().'/gm-import-'.uniqid();
        File::ensureDirectoryExists("$tmp/proto/media/11111111-2222-4333-8444-555555555555");
        File::ensureDirectoryExists("$tmp/storage/app");
        $this->app->useStoragePath("$tmp/storage");
        file_put_contents("$tmp/proto/media/11111111-2222-4333-8444-555555555555/00-abc.jpg", 'jpg');

        $pdo = new PDO("sqlite:$tmp/proto/party.sqlite");
        $pdo->exec("CREATE TABLE households (id INTEGER PRIMARY KEY, token TEXT, label TEXT, names_json TEXT, relation TEXT, created_at TEXT, last_seen TEXT);
            CREATE TABLE memories (id TEXT PRIMARY KEY, household_id INTEGER, authors_json TEXT, title TEXT, body TEXT, year INTEGER, tags_json TEXT, is_wish INTEGER, status TEXT, created_at TEXT, updated_at TEXT, wish_text TEXT);
            CREATE TABLE media (id INTEGER PRIMARY KEY, memory_id TEXT, kind TEXT, file TEXT, thumb TEXT, mime TEXT, width INTEGER, height INTEGER, duration REAL, bytes INTEGER, sort INTEGER, created_at TEXT);
            INSERT INTO households VALUES (1, 'k7m2xq9pza', 'Petra a Honza', '[\"Petra\",\"Honza\"]', 'spolužáci', '2026-11-01T10:00:00+01:00', NULL);
            INSERT INTO memories VALUES ('11111111-2222-4333-8444-555555555555', 1, '[\"Petra\"]', 'Šumava', 'Zapadli jsme', 1998, '[\"Cesty\"]', 1, 'visible', '2026-11-02T10:00:00+01:00', '2026-11-02T10:00:00+01:00', 'Vše nejlepší');
            INSERT INTO media VALUES (1, '11111111-2222-4333-8444-555555555555', 'photo', '00-abc.jpg', NULL, 'image/jpeg', 100, 80, NULL, 3, 0, '2026-11-02T10:00:00+01:00');");

        $this->artisan('gratulomat:event', ['slug' => 'adamovych-50', '--title' => 'Adamových 50', '--name' => 'Adam', '--year' => 1977, '--date' => '2027-01-23 18:00', '--test' => true])->assertSuccessful();
        $this->artisan('gratulomat:import-adam50', ['slug' => 'adamovych-50', 'data' => "$tmp/proto"])->assertSuccessful();
        $this->artisan('gratulomat:import-adam50', ['slug' => 'adamovych-50', 'data' => "$tmp/proto"])->assertSuccessful(); // opakovaně bez duplicit

        $event = Event::where('slug', 'adamovych-50')->first();
        $this->assertSame(1, $event->households()->count());
        $this->assertSame('k7m2xq9pza', $event->households()->first()->token);
        $memory = $event->memories()->first();
        $this->assertSame('Vše nejlepší', $memory->wish_text);
        $this->assertTrue($memory->wish_public);
        $this->assertFileExists($memory->dir().'/00-abc.jpg');
        $this->assertFileExists($memory->dir().'/memory.json');

        $this->get('/adamovych-50/g/k7m2xq9pza')->assertRedirect('/adamovych-50');

        File::deleteDirectory($tmp);
    }

    public function test_reserved_slug_is_refused(): void
    {
        $this->artisan('gratulomat:event', ['slug' => 'admin'])->assertFailed();
    }
}
