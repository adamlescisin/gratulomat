<?php

namespace App\Mail;

use App\Domain\Guest\EventRules;
use App\Models\Event;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Předání oslavenci (u smutečního rozloučení rodině): odkaz na prezentaci a všechny vzpomínky. */
class RecipientHandover extends Mailable
{
    public function __construct(public Event $event, public User $sender) {}

    public function envelope(): Envelope
    {
        $e = $this->event;

        return new Envelope(
            subject: $e->isFuneral() ? 'Vzpomínky na '.$e->form('acc').' – '.$e->title : 'Dárek pro vás: '.$e->title,
            replyTo: [new Address($this->sender->email)],
        );
    }

    public function content(): Content
    {
        $e = $this->event;
        $people = $e->memories()->where('status', 'visible')->get(['authors'])->pluck('authors')->flatten()->unique()->count();

        return new Content(markdown: 'mail.recipient-handover', with: [
            'event' => $e, 'h' => $e->handover(), 'url' => $e->handoverUrl(),
            // „1 člověk se podělil“, „3 lidé se podělili“, „12 lidí se podělilo“
            'who' => $people.' '.match (true) {
                $people === 1 => 'člověk', $people >= 2 && $people <= 4 => 'lidé', default => 'lidí'
            },
            'suffix' => match (true) {
                $people === 1 => '', $people >= 2 && $people <= 4 => 'i', default => 'o'
            },
            'offline' => EventRules::has($e, 'offline'),
            'archive' => $e->archive_until?->timezone('Europe/Prague')->locale('cs')->isoFormat('D. MMMM YYYY'),
        ]);
    }
}
