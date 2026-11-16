<?php

namespace App\Providers;

use App\Domain\Pricing\PriceList;
use App\Support\MailBrand;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Address;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ceník z back-office (základní ceny a plošné akce) přebíjí výchozí ceny z config/gratulomat.php
        $this->app->booted(fn () => PriceList::apply());

        // Jméno odesílatele z back-office (E-maily); adresa zůstává z MAIL_FROM_ADDRESS
        Event::listen(MessageSending::class, function (MessageSending $e) {
            $name = MailBrand::fromName();
            $e->message->from(...array_map(fn (Address $a) => new Address($a->getAddress(), $name), $e->message->getFrom()));
        });
    }
}
