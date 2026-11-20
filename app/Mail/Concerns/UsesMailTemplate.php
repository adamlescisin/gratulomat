<?php

namespace App\Mail\Concerns;

use App\Support\MailTemplates;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail s textem z upravitelné šablony (back-office → E-maily → Šablony).
 * Třída dodá klíč šablony a data: proměnné, příznaky pro [pokud …] a odkazy tlačítek.
 */
trait UsesMailTemplate
{
    private ?array $renderedTemplate = null;

    abstract public function templateKey(): string;

    /** @return array{vars?:array, flags?:array, buttons?:array} */
    abstract public function templateData(): array;

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->renderedTemplate()['subject']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.template', with: ['segments' => $this->renderedTemplate()['segments']]);
    }

    protected function renderedTemplate(): array
    {
        return $this->renderedTemplate ??= MailTemplates::render($this->templateKey(), $this->templateData());
    }
}
