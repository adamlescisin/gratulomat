<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Plánovač (cron na serveru: * * * * * php artisan schedule:run)
Schedule::command('gratulomat:archive')->dailyAt('03:15')->timezone('Europe/Prague')->withoutOverlapping();
Schedule::command('gratulomat:guest-mail')->everyMinute()->withoutOverlapping(10);
