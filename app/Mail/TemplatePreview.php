<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Support\MailTemplates;
use Illuminate\Mail\Mailable;

/** Náhled / zkušební odeslání šablony se vzorovými daty (případně s neuloženým textem z editoru). */
class TemplatePreview extends Mailable
{
    use UsesMailTemplate;

    public function __construct(public string $key, public array $data, public ?array $text = null, public bool $test = false) {}

    public function templateKey(): string
    {
        return $this->key;
    }

    public function templateData(): array
    {
        return $this->data;
    }

    protected function renderedTemplate(): array
    {
        $r = MailTemplates::render($this->key, $this->data, $this->text);
        if ($this->test) {
            $r['subject'] = '[TEST] '.$r['subject'];
        }

        return $r;
    }
}
