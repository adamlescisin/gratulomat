<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ArchiveEnding extends Mailable
{
    public function __construct(public Event $event) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Archiv události „'.$this->event->title.'“ brzy končí');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.archive-ending');
    }
}
