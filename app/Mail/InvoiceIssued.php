<?php

namespace App\Mail;

use App\Mail\Concerns\UsesMailTemplate;
use App\Models\Invoice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;

/** Daňový doklad z MyÚčta (PDF v příloze). Text: back-office → E-maily → Šablony („invoice“). */
class InvoiceIssued extends Mailable
{
    use UsesMailTemplate;

    public function __construct(public Invoice $invoice) {}

    public function templateKey(): string
    {
        return 'invoice';
    }

    public function templateData(): array
    {
        $credit = $this->invoice->kind === 'credit_note';

        return [
            'vars' => [
                'typ_dokladu' => $credit ? 'Opravný daňový doklad' : 'Daňový doklad', 'typ_dokladu_maly' => $credit ? 'opravný daňový doklad' : 'daňový doklad',
                'cislo_dokladu' => (string) $this->invoice->number, 'cislo_objednavky' => (string) $this->invoice->order?->number,
            ],
            'flags' => ['dobropis' => $credit],
        ];
    }

    public function attachments(): array
    {
        return [Attachment::fromStorageDisk('local', $this->invoice->pdf_path)->as(($this->invoice->number ?: 'doklad').'.pdf')->withMime('application/pdf')];
    }
}
