<?php

namespace App\Console\Commands;

use App\Domain\Guest\EventRules;
use App\Mail\GuestInvitation;
use App\Models\Event;
use App\Models\Household;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Každou minutu (plánovač): odešle frontu pozvánek a připomínek po dávkách (limit Resend ~2 e-maily/s)
 * a zařadí automatické připomínky 3 dny před uzávěrkou sběru (tarif s funkcí „reminders“).
 */
class SendGuestMail extends Command
{
    protected $signature = 'gratulomat:guest-mail {--batch=25}';

    protected $description = 'Odeslání pozvánek a připomínek hostům e-mailem';

    public function handle(): int
    {
        $this->queueReminders();

        $sent = 0;
        $batch = (int) $this->option('batch');
        foreach (['invite' => false, 'reminder' => true] as $kind => $reminder) {
            $queue = Household::with('event')->whereNotNull("{$kind}_queued_at")
                ->whereNull($kind === 'invite' ? 'invited_at' : 'reminded_at')->orderBy("{$kind}_queued_at")->limit($batch - $sent)->get();
            foreach ($queue as $h) {
                if (! $h->mailable() || $h->event->isArchived()) {
                    $h->update(["{$kind}_queued_at" => null]);

                    continue;
                }
                try {
                    Mail::to($h->email)->send(new GuestInvitation($h, $reminder));
                    $h->update([$kind === 'invite' ? 'invited_at' : 'reminded_at' => now()]);
                    $sent++;
                    usleep(550_000);
                } catch (Throwable $e) {
                    Log::warning('E-mail hostovi se nepodařilo odeslat: '.$e->getMessage(), ['household' => $h->id]);
                }
            }
        }
        if ($sent) {
            $this->info("Odesláno: $sent");
        }

        return self::SUCCESS;
    }

    private function queueReminders(): void
    {
        $events = Event::whereNotNull('collect_until')->whereBetween('collect_until', [now()->addDays(2), now()->addDays(3)])->get()
            ->filter(fn (Event $e) => EventRules::has($e, 'reminders') && $e->setting('reminders', true) && ! $e->setting('reminders_queued'));
        foreach ($events as $e) {
            $e->households()->whereNotNull('email')->where('email_opt_out', false)->whereNull('reminder_queued_at')
                ->whereDoesntHave('memories')->update(['reminder_queued_at' => now()]);
            $e->settings = [...($e->settings ?? []), 'reminders_queued' => now()->toIso8601String()];
            $e->save();
        }
    }
}
