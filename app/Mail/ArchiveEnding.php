<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\Event;
use Illuminate\Mail\Mailable;

/** Upozornění na blížící se konec archivu. Text: back-office → E-maily → Šablony („archive_ending“). */
class ArchiveEnding extends Mailable
{
    use UsesMailTemplate;

    public function __construct(public Event $event) {}

    public function templateKey(): string
    {
        return 'archive_ending';
    }

    public function templateData(): array
    {
        return [
            'vars' => [
                'nazev_udalosti' => $this->event->title, 'archiv_do' => $this->event->archive_until->locale('cs')->isoFormat('LL'),
                'dny_do_smazani' => (string) Event::PURGE_GRACE_DAYS,
            ],
            'buttons' => ['administrace' => route('organizer.admin', $this->event)],
        ];
    }
}
