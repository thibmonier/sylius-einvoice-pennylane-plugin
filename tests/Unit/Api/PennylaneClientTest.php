<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusEinvoicePennylanePlugin\Unit\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support\FacturXXml;
use Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support\ScriptedHttpClient;
use Tmonier\SyliusEinvoicePennylanePlugin\Api\PennylaneApiException;
use Tmonier\SyliusEinvoicePennylanePlugin\Api\PennylaneClient;
use Tmonier\SyliusEinvoicePennylanePlugin\Buyer\FacturXBuyerReader;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Status\TransmissionStatus;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\InvoiceDocument;

final class PennylaneClientTest extends TestCase
{
    private const TOKEN = 'secret-token-0123456789';

    private ScriptedHttpClient $http;

    protected function setUp(): void
    {
        $this->http = new ScriptedHttpClient();
    }

    public function testItSearchesTheCustomerByExternalReferenceWithTheBearerToken(): void
    {
        $this->http->enqueue(ScriptedHttpClient::json(200, ['has_more' => false, 'next_cursor' => null, 'items' => [['id' => 7, 'external_reference' => 'siren-732829320']]]));

        $id = $this->client()->findCustomerId('siren-732829320');

        self::assertSame(7, $id);
        $request = $this->http->requests[0];
        self::assertSame('GET', $request->method);
        self::assertSame('app.pennylane.com', $request->host());
        self::assertSame('/api/external/v2/customers', $request->path());
        self::assertSame('Bearer ' . self::TOKEN, $request->header('Authorization'));
        self::assertSame('[{"field":"external_reference","operator":"eq","value":"siren-732829320"}]', $request->query()['filter'] ?? null);
    }

    public function testAnUnknownCustomerIsNull(): void
    {
        $this->http->enqueue(ScriptedHttpClient::json(200, ['has_more' => false, 'next_cursor' => null, 'items' => []]));

        self::assertNull($this->client()->findCustomerId('siren-1'));
    }

    public function testItCreatesACompanyCustomerWithItsIdentifiers(): void
    {
        $this->http->enqueue(ScriptedHttpClient::json(201, ['id' => 8]));
        $buyer = (new FacturXBuyerReader())->read(FacturXXml::forCompany());

        $id = $this->client()->createCustomer($buyer, $buyer->reference());

        self::assertSame(8, $id);
        $request = $this->http->requests[0];
        self::assertSame('POST', $request->method);
        self::assertSame('/api/external/v2/customers', $request->path());
        self::assertSame([
            'external_reference' => 'siren-732829320',
            'billing_address' => ['address' => '5 rue de la Paix', 'postal_code' => '75002', 'city' => 'Paris', 'country_alpha2' => 'FR'],
            'emails' => ['achats@acme.example'],
            'customer_type' => 'company',
            'name' => 'Acme Industries SAS',
            'reg_no' => '732829320',
            'vat_number' => 'FR44732829320',
        ], $request->json());
    }

    public function testItCreatesAnIndividualCustomerWithoutCompanyFields(): void
    {
        $this->http->enqueue(ScriptedHttpClient::json(201, ['id' => 9]));
        $buyer = (new FacturXBuyerReader())->read(FacturXXml::forIndividual('Jeanne Martin'));

        $this->client()->createCustomer($buyer, $buyer->reference());

        self::assertCount(1, $this->http->requests);
        $payload = $this->http->requests[0]->json();
        self::assertSame('individual', $payload['customer_type'] ?? null);
        self::assertSame('Jeanne', $payload['first_name'] ?? null);
        self::assertSame('Martin', $payload['last_name'] ?? null);
        self::assertArrayNotHasKey('reg_no', $payload);
        self::assertArrayNotHasKey('vat_number', $payload);
    }

    public function testItImportsTheFacturXPdfAsAMultipartForTheCustomer(): void
    {
        $this->http->enqueue(ScriptedHttpClient::json(201, ['id' => 42, 'url' => 'https://app.pennylane.com/invoices/42', 'warnings' => []]));

        $id = $this->client()->importInvoice($this->document(), 7);

        self::assertSame(42, $id);
        $request = $this->http->requests[0];
        self::assertSame('POST', $request->method);
        self::assertSame('/api/external/v2/customer_invoices/e_invoices/imports', $request->path());
        self::assertStringStartsWith('multipart/form-data', (string) $request->header('Content-Type'));
        self::assertStringContainsString('name="file"; filename="FA-1.pdf"', $request->body);
        self::assertStringContainsString('%PDF-1.7 test', $request->body);
        self::assertStringContainsString('name="invoice_options"', $request->body);
        self::assertStringContainsString('{"customer_id":7}', $request->body);
    }

    #[DataProvider('failures')]
    public function testAFailedCallIsReportedWithAStatusAndWithoutTheToken(int $httpStatus, string $transmissionStatus, string $expectedInMessage): void
    {
        $this->http->enqueue(ScriptedHttpClient::json($httpStatus, ['error' => 'Bad token ' . self::TOKEN], ['retry-after: 12']));

        try {
            $this->client()->findCustomerId('siren-1');
            self::fail('A PennylaneApiException was expected.');
        } catch (PennylaneApiException $exception) {
            self::assertSame($transmissionStatus, $exception->transmissionStatus);
            self::assertStringContainsString($expectedInMessage, $exception->getMessage());
            self::assertStringNotContainsString(self::TOKEN, $exception->getMessage());
        }
        self::assertCount(1, $this->http->requests, 'No automatic retry.');
    }

    /** @return iterable<string, array{int, string, string}> */
    public static function failures(): iterable
    {
        yield '401 invalid token' => [401, TransmissionStatus::ERROR, 'HTTP 401'];
        yield '403 missing scope' => [403, TransmissionStatus::ERROR, 'HTTP 403'];
        yield '429 quota with Retry-After' => [429, TransmissionStatus::ERROR, 'Retry in 12 seconds'];
        yield '500 server error' => [500, TransmissionStatus::ERROR, 'HTTP 500'];
        yield '503 unavailable' => [503, TransmissionStatus::ERROR, 'HTTP 503'];
        yield '422 refused' => [422, TransmissionStatus::REJECTED, 'HTTP 422'];
    }

    public function testATimeoutIsAnErrorThatCanBeRelaunched(): void
    {
        $this->http->enqueue(ScriptedHttpClient::timeout());

        try {
            $this->client()->findCustomerId('siren-1');
            self::fail('A PennylaneApiException was expected.');
        } catch (PennylaneApiException $exception) {
            self::assertSame(TransmissionStatus::ERROR, $exception->transmissionStatus);
            self::assertStringContainsString('unavailable', $exception->getMessage());
        }
    }

    public function testWithoutTokenNoRequestIsSent(): void
    {
        try {
            $this->client(token: null)->findCustomerId('siren-1');
            self::fail('A PennylaneApiException was expected.');
        } catch (PennylaneApiException $exception) {
            self::assertSame(TransmissionStatus::ERROR, $exception->transmissionStatus);
            self::assertStringContainsString('token is not configured', $exception->getMessage());
        }
        self::assertSame([], $this->http->requests);
    }

    private function client(?string $token = self::TOKEN): PennylaneClient
    {
        return new PennylaneClient($this->http, 'https://app.pennylane.com', $token, 10.0);
    }

    private function document(): InvoiceDocument
    {
        return new InvoiceDocument('invoice-1', 'FA-1', FacturXXml::forCompany(), '%PDF-1.7 test', 'FA-1.pdf');
    }
}
