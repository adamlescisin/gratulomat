<?php

namespace App\Mail;

use App\Http\Controllers\Organizer\EventAdminController;
use App\Models\Household;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/** Pozvánka hostovi (text z administrace) nebo připomínka před uzávěrkou sběru. */
class GuestInvitation extends Mailable
{
    public function __construct(public Household $household, public bool $reminder = false) {}

    public function envelope(): Envelope
    {
        $e = $this->household->event;
        $owner = $e->organizers()->wherePivot('role', 'owner')->first();

        return new Envelope(
            subject: $this->reminder ? 'Připomínka: '.$e->title.' – ještě můžete přispět' : ($e->isFuneral() ? 'Pojďte se podělit o vzpomínky: ' : 'Pojďte spolutvořit událost ').$e->title,
            replyTo: $owner ? [new Address($owner->email)] : [],
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->household->unsubscribeUrl().'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
    }

    public function content(): Content
    {
        $e = $this->household->event;
        $text = $e->fillDate(str_replace(['{jmena}', '{odkaz}'], [implode(' a ', $this->household->names), ''],
            $e->setting('invite_text') ?: EventAdminController::defaultInviteFor($e)));

        return new Content(markdown: 'mail.guest-invitation', with: [
            'event' => $e, 'text' => trim(preg_replace("/\n{3,}/", "\n\n", $text)),
            'deadline' => $e->refDate()->timezone('Europe/Prague')->locale('cs')->isoFormat('D. MMMM'),
            'url' => $this->household->inviteUrl(), 'unsubscribe' => $this->household->unsubscribeUrl(),
        ]);
    }
}
