<x-mail::message>
# {{ $invoice->kind === 'credit_note' ? 'Opravný daňový doklad' : 'Daňový doklad' }} {{ $invoice->number }}

V příloze posíláme {{ $invoice->kind === 'credit_note' ? 'opravný daňový doklad' : 'daňový doklad' }} k objednávce {{ $invoice->order->number }}. Doklad je uhrazený kartou.

Gratulomat
</x-mail::message>
