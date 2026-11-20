<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/** Jednorázový přihlašovací odkaz organizátorovi. Text: back-office → E-maily → Šablony („login_link“). */
class LoginLink extends Mailable
{
    use Queueable, UsesMailTemplate;

    public function __construct(public User $user, public string $link) {}

    public function templateKey(): string
    {
        return 'login_link';
    }

    public function templateData(): array
    {
        return ['vars' => ['jmeno' => (string) $this->user->name], 'buttons' => ['prihlaseni' => $this->link]];
    }
}
