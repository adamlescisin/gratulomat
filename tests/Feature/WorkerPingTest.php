<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerPingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_records_heartbeat_shown_in_backoffice(): void
    {
        config(['services.worker.token' => 'wt', 'gratulomat.operators' => ['op@gratulomat.cz']]);
        $this->getJson('/api/worker/ping')->assertUnauthorized();
        $this->getJson('/api/worker/ping', ['Authorization' => 'Bearer wt', 'X-Worker-Host' => 'mac-mini', 'X-Worker-Version' => '1.1'])
            ->assertOk()->assertJson(['ok' => true, 'queued' => 0]);
        $this->assertSame('mac-mini', Setting::get('worker_heartbeat')['host']);

        $this->actingAs(User::create(['email' => 'op@gratulomat.cz', 'name' => 'op']));
        $this->get('/backoffice')->assertSee('V pořádku')->assertSee('mac-mini');
    }
}
