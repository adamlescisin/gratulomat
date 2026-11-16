<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Worker\InvoiceJobHandler;
use Worker\MyUctoClient;

class InvoiceJobHandlerTest extends TestCase
{
    /** @var list<array{string,string,?array}> */
    private array $calls = [];

    private function handler(array $routes): InvoiceJobHandler
    {
        $transport = function (string $method, string $url, ?array $body) use ($routes) {
            $this->calls[] = [$method, $url, $body];
            $path = parse_url($url, PHP_URL_PATH);
            foreach ($routes as $route => $response) {
                [$m, $p] = explode(' ', $route, 2);
                if ($m === $method && $p === $path) {
                    return [200, is_string($response) ? $response : json_encode($response)];
                }
            }

            return [404, '{"error":"not found"}'];
        };

        return new InvoiceJobHandler(
            new MyUctoClient('http://myucto.test', 'mi_pat_x', null, $transport),
            ['street' => 'Hlavní 1', 'city' => 'Hostín', 'zip' => '27732'],
        );
    }

    private function job(array $override = []): array
    {
        return array_replace([
            'order_number' => 'G-2027-0001',
            'paid_at' => '2027-01-10',
            'stripe_payment' => 'pi_123',
            'vat_rate' => 21,
            'customer' => ['email' => 'jana@example.com', 'name' => 'Jana'],
            'lines' => [['name' => 'Tarif Párty', 'qty' => 1, 'unit_price_halere' => 99000]],
        ], $override);
    }

    private function body(string $method, string $path): ?array
    {
        foreach ($this->calls as [$m, $url, $body]) {
            if ($m === $method && parse_url($url, PHP_URL_PATH) === $path) {
                return $body;
            }
        }
        $this->fail("Volání {$method} {$path} neproběhlo.");
    }

    public function test_consumer_invoice_flow(): void
    {
        $result = $this->handler([
            'GET /api/v1/invoices' => ['data' => []],
            'GET /api/v1/clients' => ['data' => []],
            'POST /api/v1/clients' => ['id' => 7],
            'GET /api/v1/codebooks/vat-rates' => ['data' => [['id' => 1, 'rate' => 0], ['id' => 3, 'rate' => 21]]],
            'POST /api/v1/invoices' => ['id' => 55, 'status' => 'draft'],
            'POST /api/v1/invoices/55/issue' => ['id' => 55, 'status' => 'issued', 'varsymbol' => '2027001'],
            'POST /api/v1/invoices/55/mark-paid' => ['id' => 55, 'status' => 'paid', 'varsymbol' => '2027001', 'paid_at' => '2027-01-10'],
            'GET /api/v1/invoices/55/pdf' => '%PDF-1.7',
        ])->handle($this->job());

        $this->assertSame(['myucto_id' => 55, 'number' => '2027001', 'pdf' => '%PDF-1.7'], $result);

        $client = $this->body('POST', '/api/v1/clients');
        $this->assertSame(InvoiceJobHandler::CONSUMER_CLIENT, $client['company_name']);

        $draft = $this->body('POST', '/api/v1/invoices');
        $this->assertSame(7, $draft['client_id']);
        $this->assertSame('2027-01-10', $draft['tax_date']);
        $this->assertSame('card', $draft['payment_method']);
        $this->assertTrue($draft['prices_include_vat']);
        $this->assertSame(3, $draft['items'][0]['vat_rate_id']);
        $this->assertEquals(990, $draft['items'][0]['unit_price_without_vat']);
        $this->assertStringContainsString('G-2027-0001', $draft['note_below_items']);
        $this->assertStringContainsString('jana@example.com', $draft['note_below_items']);

        $this->assertSame(['paid_at' => '2027-01-10'], $this->body('POST', '/api/v1/invoices/55/mark-paid'));
    }

    public function test_company_client_is_created_from_ico(): void
    {
        $this->handler([
            'GET /api/v1/invoices' => ['data' => []],
            'GET /api/v1/clients' => ['data' => [['id' => 1, 'ic' => '99999999']]],
            'POST /api/v1/clients' => ['id' => 8],
            'GET /api/v1/codebooks/vat-rates' => ['data' => [['id' => 3, 'rate' => 21]]],
            'POST /api/v1/invoices' => ['id' => 56, 'status' => 'draft'],
            'POST /api/v1/invoices/56/issue' => ['id' => 56, 'status' => 'issued', 'varsymbol' => '2027002'],
            'POST /api/v1/invoices/56/mark-paid' => ['id' => 56, 'paid_at' => '2027-01-10', 'varsymbol' => '2027002'],
            'GET /api/v1/invoices/56/pdf' => '%PDF',
        ])->handle($this->job(['customer' => [
            'email' => 'fakturace@firma.cz', 'name' => 'Petr',
            'company' => ['ico' => '12345678', 'dic' => 'CZ12345678', 'name' => 'Firma s.r.o.', 'street' => 'Dlouhá 2', 'city' => 'Praha', 'zip' => '11000'],
        ]]));

        $client = $this->body('POST', '/api/v1/clients');
        $this->assertSame('12345678', $client['ic']);
        $this->assertSame('CZ12345678', $client['dic']);
        $this->assertSame(8, $this->body('POST', '/api/v1/invoices')['client_id']);
    }

    public function test_retry_continues_existing_invoice_without_duplicating(): void
    {
        $result = $this->handler([
            'GET /api/v1/invoices' => ['data' => [['invoices' => [['id' => 55, 'status' => 'issued', 'varsymbol' => '2027001']]]]],
            'POST /api/v1/invoices/55/mark-paid' => ['id' => 55, 'varsymbol' => '2027001', 'paid_at' => '2027-01-10'],
            'GET /api/v1/invoices/55/pdf' => '%PDF',
        ])->handle($this->job());

        $this->assertSame(55, $result['myucto_id']);
        foreach ($this->calls as [$method, $url]) {
            $this->assertFalse($method === 'POST' && parse_url($url, PHP_URL_PATH) === '/api/v1/invoices', 'Nesmí vzniknout druhý doklad.');
        }
    }
}
