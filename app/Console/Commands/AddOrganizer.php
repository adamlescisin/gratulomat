<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use Illuminate\Console\Command;

/** Přidá organizátora k události a vypíše přihlašovací odkaz (pro betu bez e-mailu). */
class AddOrganizer extends Command
{
    protected $signature = 'gratulomat:organizer {slug} {email} {--name=} {--role=owner} {--days=7 : platnost odkazu ve dnech}';

    protected $description = 'Přidá organizátora k události a vypíše přihlašovací odkaz';

    public function handle(): int
    {
        $event = Event::where('slug', $this->argument('slug'))->firstOrFail();
        $email = mb_strtolower(trim($this->argument('email')));
        $user = User::firstOrCreate(['email' => $email], ['name' => $this->option('name') ?: strstr($email, '@', true)]);
        $event->organizers()->syncWithoutDetaching([$user->id => ['role' => $this->option('role')]]);

        $this->info("{$email} je organizátor události {$event->title}.");
        $this->line('Přihlašovací odkaz (jednorázový): '.$user->loginLink(60 * 24 * (int) $this->option('days')));

        return self::SUCCESS;
    }
}
