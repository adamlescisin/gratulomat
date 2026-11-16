<?php

namespace Worker;

use RuntimeException;

/**
 * Minimální klient REST API MyÚčto (/api/v1, Bearer mi_pat_…).
 * Běží na Mac Mini vedle lokální instance MyÚčto; token nikdy neopouští Mac Mini.
 */
class MyUctoClient
{
    /** @var callable|null testovací náhrada HTTP vrstvy: fn(string, string, ?array): array{int, string} */
    private $transport;

    private ?array $vatRates = null;

    public function __construct(
        private readonly string $baseUrl,      // např. http://127.0.0.1:8080
        private readonly string $token,        // mi_pat_…
        private readonly ?int $supplierId = null,
        ?callable $transport = null,
    ) {
        $this->transport = $transport;
    }

    public function findClientByIco(string $ico): ?array
    {
        $res = $this->request('GET', '/api/v1/clients?'.http_build_query(['q' => $ico, 'per_page' => 5]));
        foreach ($res['data'] ?? [] as $client) {
            if (($client['ic'] ?? null) === $ico) {
                return $client;
            }
        }

        return null;
    }

    public function findClientByName(string $name): ?array
    {
        $res = $this->request('GET', '/api/v1/clients?'.http_build_query(['q' => $name, 'per_page' => 5]));
        foreach ($res['data'] ?? [] as $client) {
            if (($client['company_name'] ?? null) === $name) {
                return $client;
            }
        }

        return null;
    }

    public function createClient(array $input): array
    {
        return $this->request('POST', '/api/v1/clients', $input);
    }

    /** ID sazby DPH z číselníku /codebooks/vat-rates podle procenta. */
    public function vatRateId(float $percent): int
    {
        $this->vatRates ??= $this->request('GET', '/api/v1/codebooks/vat-rates');
        foreach ($this->vatRates['data'] ?? $this->vatRates as $rate) {
            if (abs((float) ($rate['rate'] ?? $rate['value'] ?? -1) - $percent) < 0.001) {
                return (int) $rate['id'];
            }
        }
        throw new RuntimeException("Sazba DPH {$percent} % v MyÚčtu nenalezena.");
    }

    /** Hledání dokladu podle textu (číslo objednávky v poznámce) kvůli idempotenci. */
    public function findInvoiceByText(string $text): ?array
    {
        $res = $this->request('GET', '/api/v1/invoices?'.http_build_query(['q' => $text, 'per_page' => 5]));
        foreach ($res['data'] ?? [] as $group) {
            foreach ($group['invoices'] ?? [$group] as $invoice) {
                if (isset($invoice['id'])) {
                    return $invoice;
                }
            }
        }

        return null;
    }

    public function createDraft(array $input): array
    {
        return $this->request('POST', '/api/v1/invoices', $input);
    }

    public function issue(int $id): array
    {
        return $this->request('POST', "/api/v1/invoices/{$id}/issue");
    }

    public function markPaid(int $id, string $paidAt): array
    {
        return $this->request('POST', "/api/v1/invoices/{$id}/mark-paid", ['paid_at' => $paidAt]);
    }

    /** Vytvoří koncept dobropisu; vrací credit_note_id. */
    public function createCreditNoteDraft(int $id, string $reason): int
    {
        $res = $this->request('POST', "/api/v1/invoices/{$id}/cancel", ['mode' => 'credit_note', 'reason' => $reason]);

        return (int) $res['credit_note_id'];
    }

    public function getInvoice(int $id): array
    {
        return $this->request('GET', "/api/v1/invoices/{$id}");
    }

    public function updateInvoice(int $id, array $input): array
    {
        return $this->request('PUT', "/api/v1/invoices/{$id}", $input);
    }

    public function pdf(int $id): string
    {
        return $this->raw('GET', "/api/v1/invoices/{$id}/pdf");
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $raw = $this->raw($method, $path, $body);

        return $raw === '' ? [] : json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    }

    private function raw(string $method, string $path, ?array $body = null): string
    {
        $url = rtrim($this->baseUrl, '/').$path;
        [$status, $content] = $this->transport
            ? ($this->transport)($method, $url, $body)
            : $this->curl($method, $url, $body);

        if ($status >= 400) {
            throw new RuntimeException("MyÚčto {$method} {$path} → HTTP {$status}: ".substr($content, 0, 500));
        }

        return $content;
    }

    /** @return array{int, string} */
    private function curl(string $method, string $url, ?array $body): array
    {
        $headers = ['Authorization: Bearer '.$this->token, 'Accept: application/json'];
        if ($this->supplierId) {
            $headers[] = 'X-Supplier-Id: '.$this->supplierId;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $body !== null ? [...$headers, 'Content-Type: application/json'] : $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $content = curl_exec($ch);
        if ($content === false) {
            throw new RuntimeException('MyÚčto nedostupné: '.curl_error($ch));
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [$status, $content];
    }
}
