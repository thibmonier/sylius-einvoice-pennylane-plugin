<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusEinvoicePennylanePlugin\Unit\Transmission;

use PHPUnit\Framework\TestCase;
use Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support\FacturXXml;
use Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support\ScriptedHttpClient;
use Tmonier\SyliusEinvoicePennylanePlugin\Api\PennylaneClient;
use Tmonier\SyliusEinvoicePennylanePlugin\Buyer\FacturXBuyerReader;
use Tmonier\SyliusEinvoicePennylanePlugin\Transmission\PennylaneInvoiceTransmitter;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Status\TransmissionStatus;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\InvoiceDocument;

/** The scenarios of US-283 on the adapter alone; the HTTP client is scripted. */
final class PennylaneInvoiceTransmitterTest extends TestCase
{
    private const TOKEN = 'secret-token-0123456789';

    private ScriptedHttpClient $http;

    protected function setUp(): void
    {
        $this->http = new ScriptedHttpClient();
    }

    /** Scenario: Facture transmise (nominal) */
    public function testTheCustomerIsCreatedThenTheInvoiceIsImported(): void
    {
        $this->http->enqueue(
            ScriptedHttpClient::json(200, ['has_more' => false, 'next_cursor' => null, 'items' => []]),
            ScriptedHttpClient::json(201, ['id' => 7]),
            ScriptedHttpClient::json(201, ['id' => 42, 'url' => 'https://app.pennylane.com/invoices/42', 'warnings' => []]),
        );

        $result = $this->transmitter()->transmit($this->document());

        self::assertSame(TransmissionStatus::DEPOSITED, $result->status);
        self::assertStringContainsString('invoice id 42', (string) $result->message);
        self::assertSame(['GET', 'POST', 'POST'], array_map(static fn (\Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support\RecordedRequest $request): string => $request->method, $this->http->requests));
        self::assertSame('siren-732829320', $this->http->requests[1]->json()['external_reference'] ?? null);
        self::assertStringContainsString('{"customer_id":7}', $this->http->requests[2]->body);
    }

    /** Scenario: Client déjà connu (alternatif) */
    public function testAKnownCustomerIsNotCreatedAgain(): void
    {
        $this->http->enqueue(
            ScriptedHttpClient::json(200, ['has_more' => false, 'next_cursor' => null, 'items' => [['id' => 7]]]),
            ScriptedHttpClient::json(201, ['id' => 43, 'url' => 'u', 'warnings' => []]),
        );

        $result = $this->transmitter()->transmit($this->document());

        self::assertSame(TransmissionStatus::DEPOSITED, $result->status);
        self::assertCount(2, $this->http->requests);
        self::assertSame('/api/external/v2/customer_invoices/e_invoices/imports', $this->http->requests[1]->path());
        self::assertStringContainsString('{"customer_id":7}', $this->http->requests[1]->body);
    }

    /** Scenario: Jeton invalide ou portée insuffisante (erreur) */
    public function testARefusedTokenEndsInErrorWithoutTheTokenAndWithoutRetry(): void
    {
        $this->http->enqueue(ScriptedHttpClient::json(401, ['error' => 'The access token is invalid ' . self::TOKEN, 'status' => 401]));

        $result = $this->transmitter()->transmit($this->document());

        self::assertSame(TransmissionStatus::ERROR, $result->status);
        self::assertStringContainsString('HTTP 401', (string) $result->message);
        self::assertStringNotContainsString(self::TOKEN, (string) $result->message);
        self::assertCount(1, $this->http->requests);
    }

    /** Scenario: Pennylane indisponible ou quota atteint (erreur) */
    public function testAnUnavailablePennylaneEndsInErrorThatCanBeRelaunched(): void
    {
        $this->http->enqueue(
            ScriptedHttpClient::json(200, ['has_more' => false, 'next_cursor' => null, 'items' => [['id' => 7]]]),
            ScriptedHttpClient::json(503, ['error' => 'down']),
        );

        $result = $this->transmitter()->transmit($this->document());

        self::assertSame(TransmissionStatus::ERROR, $result->status);
        self::assertStringContainsString('relaunched', (string) $result->message);
    }

    public function testAnImportRefusedByPennylaneIsRejected(): void
    {
        $this->http->enqueue(
            ScriptedHttpClient::json(200, ['has_more' => false, 'next_cursor' => null, 'items' => [['id' => 7]]]),
            ScriptedHttpClient::json(422, ['error' => 'invalid']),
        );

        self::assertSame(TransmissionStatus::REJECTED, $this->transmitter()->transmit($this->document())->status);
    }

    public function testADocumentWithoutBuyerSendsNothing(): void
    {
        $document = new InvoiceDocument('invoice-1', 'FA-1', FacturXXml::withoutBuyer(), '%PDF', 'FA-1.pdf');

        $result = $this->transmitter()->transmit($document);

        self::assertSame(TransmissionStatus::ERROR, $result->status);
        self::assertSame([], $this->http->requests);
    }

    private function transmitter(): PennylaneInvoiceTransmitter
    {
        return new PennylaneInvoiceTransmitter(new PennylaneClient($this->http, 'https://app.pennylane.com', self::TOKEN, 10.0), new FacturXBuyerReader());
    }

    private function document(): InvoiceDocument
    {
        return new InvoiceDocument('invoice-1', 'FA-1', FacturXXml::forCompany(), '%PDF-1.7 test', 'FA-1.pdf');
    }
}
