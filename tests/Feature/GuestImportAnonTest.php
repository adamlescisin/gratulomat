<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class GuestImportAnonTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $plan = 'premiera'): Event
    {
        $e = Event::create(['slug' => 'janinych-60', 'title' => 'Janiných 60', 'recipient_name' => 'Jana', 'event_date' => now()->addMonth(), 'plan' => $plan]);
        $u = User::create(['email' => 'org@example.cz', 'name' => 'org']);
        $e->organizers()->attach($u->id, ['role' => 'owner']);
        $this->actingAs($u);

        return $e;
    }

    public function test_csv_import_with_czech_excel_encoding_and_header(): void
    {
        $e = $this->event();
        $e->households()->create(['token' => Household::newToken(), 'label' => 'Petra a Honza', 'names' => ['Petra', 'Honza']]);
        $csv = iconv('UTF-8', 'Windows-1250', "Jméno;Příjmení;E-mail;Vztah\nPetra a Honza;Novákovi;p@x.cz;sousedé\nŽofie;Malá;ZOFIE@X.CZ;teta\nMartin;;neplatny;kolega\n");
        $file = UploadedFile::fake()->createWithContent('hoste.csv', $csv);

        $this->post('/janinych-60/admin/api/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['added' => 2, 'duplicates' => 1]);
        $z = $e->households()->where('label', 'Žofie')->first();
        $this->assertSame('zofie@x.cz', $z->email);
        $this->assertSame('teta', $z->relation);
        $this->assertSame('import', $z->source);
        $this->assertNull($e->households()->where('label', 'Martin')->first()->email);
    }

    public function test_xlsx_import_without_header(): void
    {
        $e = $this->event();
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Jana a Pavel</t></si><si><t>jp@x.cz</t></si><si><r><t>Ota</t></r></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row><row r="2"><c r="A2" t="s"><v>2</v></c><c r="C2" t="inlineStr"><is><t>bratr</t></is></c></row></sheetData></worksheet>');
        $zip->close();

        $this->post('/janinych-60/admin/api/import', ['file' => new UploadedFile($path, 'seznam.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['added' => 2]);
        $this->assertSame('jp@x.cz', $e->households()->where('label', 'Jana a Pavel')->first()->email);
        $this->assertSame(['Jana', 'Pavel'], $e->households()->where('label', 'Jana a Pavel')->first()->names);
        @unlink($path);
    }

    public function test_import_and_anon_require_premiera(): void
    {
        $this->event('party');
        $this->post('/janinych-60/admin/api/import', ['file' => UploadedFile::fake()->createWithContent('a.csv', "Jana\n")], ['Accept' => 'application/json'])->assertForbidden();
        $this->postJson('/janinych-60/admin/api/anon-link', ['action' => 'enable'])->assertForbidden();
        $this->getJson('/janinych-60/admin/api/state')->assertJson(['event' => ['import_available' => false, 'anon_available' => false]]);
    }

    public function test_anonymous_link_lets_guest_join(): void
    {
        $e = $this->event();
        $link = $this->postJson('/janinych-60/admin/api/anon-link', ['action' => 'enable'])->assertOk()->json('anon_link');
        $this->assertStringContainsString('/janinych-60/p/', $link);
        $path = parse_url($link, PHP_URL_PATH);
        auth()->logout();

        $this->get($path)->assertOk()->assertSee('Jak se jmenujete?');
        $this->post($path, ['name' => 'X'])->assertSessionHasErrors('name');
        $this->post($path, ['name' => 'Lucka a Tom', 'relation' => 'z práce', 'email' => 'L@X.cz'])->assertRedirect('/janinych-60');
        $h = $e->households()->where('source', 'anonymous')->first();
        $this->assertSame(['Lucka', 'Tom'], $h->names);
        $this->assertSame('l@x.cz', $h->email);
        $this->get('/janinych-60')->assertOk()->assertSee(__('guest.logout'))->assertDontSee('name="kod"', false);
        // stejný odkaz znovu na stejném zařízení = nový host: předchozí se odhlásí a formulář je prázdný
        $this->get($path)->assertOk()->assertSee('Jak se jmenujete?')->assertDontSee('Lucka')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/janinych-60')->assertDontSee(__('guest.logout'));
        $this->post($path, ['name' => 'Karel'])->assertRedirect('/janinych-60');
        $this->assertSame(2, $e->households()->where('source', 'anonymous')->count());
        $this->getJson('/janinych-60/api/me')->assertJsonPath('household.label', 'Karel');

        // nový odkaz zneplatní starý
        $this->actingAs(User::where('email', 'org@example.cz')->first());
        $this->postJson('/janinych-60/admin/api/anon-link', ['action' => 'regenerate']);
        $this->postJson('/janinych-60/admin/api/anon-link', ['action' => 'disable'])->assertJson(['anon_link' => null]);
        $this->flushSession();
        $this->get($path)->assertRedirect('/janinych-60?neplatny=1');
    }

    public function test_anonymous_join_respects_guest_limit(): void
    {
        $e = $this->event();
        $e->update(['limits' => ['guests' => 1]]);
        $e->households()->create(['token' => Household::newToken(), 'label' => 'Jana', 'names' => ['Jana']]);
        $link = parse_url($this->postJson('/janinych-60/admin/api/anon-link', ['action' => 'enable'])->json('anon_link'), PHP_URL_PATH);
        auth()->logout();
        $this->get($link)->assertSee('Kapacita této události je naplněná');
        $this->post($link, ['name' => 'Karel']);
        $this->assertSame(1, $e->households()->count());
    }
}
