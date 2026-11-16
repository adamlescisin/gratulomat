<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoginLink extends Mailable
{
    use Queueable;

    public function __construct(public User $user, public string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Přihlášení do Gratulomatu');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.login-link');
    }
}
