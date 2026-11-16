<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvoiceIssued extends Mailable
{
    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->invoice->kind === 'credit_note' ? 'Opravný daňový doklad ' : 'Daňový doklad ').$this->invoice->number.' – Gratulomat');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice');
    }

    public function attachments(): array
    {
        return [Attachment::fromStorageDisk('local', $this->invoice->pdf_path)->as(($this->invoice->number ?: 'doklad').'.pdf')->withMime('application/pdf')];
    }
}
