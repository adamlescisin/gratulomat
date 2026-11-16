<?php

namespace App\Mail;

use App\Models\WaitlistEntry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WaitlistSignup extends Mailable
{
    public function __construct(public WaitlistEntry $entry, public int $total) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Čekací listina: {$this->entry->email} ({$this->total})");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.waitlist-signup');
    }
}
