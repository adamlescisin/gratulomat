<?php

namespace Worker;

/**
 * Úloha „vystav doklad“ z API Gratulomatu → doklad v MyÚčtu (specifikace „Fakturace a DPH“).
 *
 * Vstup (job.payload):
 *  order_number, paid_at (Y-m-d), stripe_payment, vat_rate (21),
 *  customer: {email, name, company?: {ico, dic, name, street, city, zip, country}},
 *  lines: [{name, qty, unit_price_halere}]   – ceny včetně DPH
 *  myucto_id? – vyplněno při opakovaném pokusu (koncept už existuje)
 *
 * Výstup: {myucto_id, number, pdf (binární)}
 */
class InvoiceJobHandler
{
    public const CONSUMER_CLIENT = 'Koncoví zákazníci gratulomat.cz';

    public function __construct(
        private readonly MyUctoClient $myucto,
        private readonly array $consumerClientAddress, // street, city, zip – adresa provozovatele pro souhrnného klienta
        private readonly ?int $brandingProfileId = null,
    ) {}

    public function handle(array $job): array
    {
        $id = $job['myucto_id'] ?? null;
        $invoice = null;

        if (! $id) {
            // Idempotence: doklad s tímto číslem objednávky už mohl vzniknout (pád workeru po vytvoření).
            $invoice = $this->myucto->findInvoiceByText($job['order_number']);
            $id = $invoice['id'] ?? null;
        }

        if (! $id) {
            $invoice = $this->myucto->createDraft($this->draftPayload($job));
            $id = (int) $invoice['id'];
        }

        $invoice ??= $this->myucto->getInvoice($id);
        if (($invoice['status'] ?? 'draft') === 'draft') {
            $invoice = $this->myucto->issue($id);
        }
        if (empty($invoice['paid_at'])) {
            $invoice = $this->myucto->markPaid($id, $job['paid_at']);
        }

        return [
            'myucto_id' => $id,
            'number' => $invoice['varsymbol'] ?? $invoice['number'] ?? null,
            'pdf' => $this->myucto->pdf($id),
        ];
    }

    /**
     * Dobropis při vrácení peněz: job = {invoice_myucto_id, refund_halere, full: bool, reason}.
     */
    public function handleCreditNote(array $job): array
    {
        $id = $job['credit_note_myucto_id']
            ?? $this->myucto->createCreditNoteDraft((int) $job['invoice_myucto_id'], $job['reason'] ?? 'Vrácení platby');

        $note = $this->myucto->getInvoice($id);
        if (($note['status'] ?? 'draft') === 'draft') {
            if (empty($job['full'])) {
                // Částečné vrácení: koncept má záporné kopie všech položek, nahradíme je jednou položkou.
                $this->myucto->updateInvoice($id, [
                    'client_id' => $note['client_id'],
                    'prices_include_vat' => true,
                    'items' => [[
                        'description' => 'Vrácení části ceny',
                        'quantity' => -1,
                        'unit' => 'ks',
                        'unit_price_without_vat' => $job['refund_halere'] / 100,
                        'vat_rate_id' => $this->myucto->vatRateId((float) ($job['vat_rate'] ?? 21)),
                    ]],
                ]);
            }
            $note = $this->myucto->issue($id);
        }

        return [
            'myucto_id' => $id,
            'number' => $note['varsymbol'] ?? null,
            'pdf' => $this->myucto->pdf($id),
        ];
    }

    public function draftPayload(array $job): array
    {
        $vatRateId = $this->myucto->vatRateId((float) ($job['vat_rate'] ?? 21));

        $items = array_map(fn (array $line) => [
            'description' => $line['name'],
            'quantity' => $line['qty'],
            'unit' => 'ks',
            // Při prices_include_vat=true MyÚčto počítá DPH shora; hodnotu zadáváme včetně DPH.
            // Ověřit na testovací firmě (viz specifikace), jinak přepnout na výpočet bez DPH.
            'unit_price_without_vat' => $line['unit_price_halere'] / 100,
            'vat_rate_id' => $vatRateId,
        ], $job['lines']);

        $note = "Objednávka {$job['order_number']} · platba kartou ({$job['stripe_payment']})";
        if (empty($job['customer']['company'])) {
            $note .= ' · zákazník: '.$job['customer']['email'];
        }

        return array_filter([
            'client_id' => $this->clientId($job['customer']),
            'branding_profile_id' => $this->brandingProfileId,
            'invoice_type' => 'invoice',
            'issue_date' => $job['paid_at'],
            'due_date' => $job['paid_at'],
            'tax_date' => $job['paid_at'],
            'currency' => 'CZK',
            'language' => 'cs',
            'prices_include_vat' => true,
            'payment_method' => 'card',
            'auto_send_reminders' => false,
            'note_below_items' => $note.' · Uhrazeno.',
            'items' => $items,
        ], fn ($v) => $v !== null);
    }

    private function clientId(array $customer): int
    {
        $company = $customer['company'] ?? null;

        if ($company) {
            $found = $this->myucto->findClientByIco($company['ico']);
            if ($found) {
                return (int) $found['id'];
            }

            return (int) $this->myucto->createClient([
                'company_name' => $company['name'],
                'ic' => $company['ico'],
                'dic' => $company['dic'] ?? null,
                'street' => $company['street'],
                'city' => $company['city'],
                'zip' => $company['zip'],
                'country_iso2' => $company['country'] ?? 'CZ',
                'main_email' => $customer['email'],
                'is_customer' => true,
                'auto_send_reminders' => false,
            ])['id'];
        }

        // Spotřebitelé: jeden souhrnný klient, zjednodušený daňový doklad (do 10 000 Kč s DPH).
        $found = $this->myucto->findClientByName(self::CONSUMER_CLIENT);
        if ($found) {
            return (int) $found['id'];
        }

        return (int) $this->myucto->createClient([
            'company_name' => self::CONSUMER_CLIENT,
            ...$this->consumerClientAddress,
            'country_iso2' => 'CZ',
            'is_customer' => true,
            'is_vat_payer' => false,
            'auto_send_reminders' => false,
        ])['id'];
    }
}
