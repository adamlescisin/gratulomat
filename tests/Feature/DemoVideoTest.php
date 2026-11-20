<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DemoVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DemoVideoTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gratulomat.site_mode' => 'live', 'gratulomat.operators' => ['op@gratulomat.cz']]);
        $this->storage = sys_get_temp_dir().'/gm-demo-'.uniqid();
        File::ensureDirectoryExists($this->storage);
        $this->app->useStoragePath($this->storage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    public function test_landing_has_demo_button_with_placeholder_when_not_set(): void
    {
        $this->get('/')->assertOk()->assertSee('Ukázkový Gratulomat')->assertSee('data-demo', false)
            ->assertSee('Ukázku právě připravujeme')->assertSee('chronologický audiovizuální příběh');
    }

    public function test_operator_uploads_demo_video_and_landing_plays_it(): void
    {
        $this->actingAs(User::create(['email' => 'op@gratulomat.cz', 'name' => 'Op']));
        $this->get('/backoffice/ukazka')->assertOk()->assertSee('Ukázka není nastavená');

        $this->post('/backoffice/ukazka', ['video' => UploadedFile::fake()->create('demo.mp4', 2048, 'video/mp4'), 'poster' => UploadedFile::fake()->image('p.jpg', 640, 360)])
            ->assertRedirect('/backoffice/ukazka');
        $demo = DemoVideo::get();
        $this->assertSame('file', $demo['type']);
        $this->assertNotNull($demo['poster']);

        $this->get(parse_url($demo['src'], PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->get('/ukazka/demo-neexistuje000000000000000.mp4')->assertNotFound();
        $this->get('/')->assertSee($demo['src'], false)->assertDontSee('Ukázku právě připravujeme');

        // soubor má přednost před odkazem; po smazání se použije odkaz
        $this->post('/backoffice/ukazka', ['url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertRedirect();
        $this->assertSame('file', DemoVideo::get()['type']);
        $this->delete('/backoffice/ukazka')->assertRedirect();
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0&modestbranding=1', DemoVideo::get()['src']);
        $this->get('/')->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_invalid_url_and_file_type_are_rejected(): void
    {
        $this->actingAs(User::create(['email' => 'op@gratulomat.cz', 'name' => 'Op']));
        $this->post('/backoffice/ukazka', ['url' => 'https://example.com/stranka'])->assertSessionHasErrors('url');
        $this->post('/backoffice/ukazka', ['video' => UploadedFile::fake()->create('x.avi', 10, 'video/x-msvideo')])->assertSessionHasErrors('video');
        $this->assertNull(DemoVideo::get());
        $this->assertSame('https://player.vimeo.com/video/76979871?autoplay=1', DemoVideo::embedUrl('https://vimeo.com/76979871'));
        $this->assertTrue(DemoVideo::isPlayableUrl('https://cdn.example.com/demo.mp4'));
    }
}
