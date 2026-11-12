<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusEinvoicePennylanePlugin\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support\FacturXXml;
use Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support\ScriptedHttpClient;
use Tmonier\SyliusEinvoicePennylanePlugin\Transmission\PennylaneInvoiceTransmitter;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Entity\InvoiceTransmission;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Status\TransmissionStatus;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\RelaunchTransmissionHandler;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\TransmissionNotRelaunchable;
use Tmonier\SyliusFacturXPlugin\Event\FacturXInvoiceGenerated;

/**
 * The adapter plugged on the listener of einvoice-transmission, in the test application (real database, scripted HTTP client).
 */
final class PennylaneWiringTest extends KernelTestCase
{
    private ScriptedHttpClient $http;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection()->beginTransaction();
        /** @var ScriptedHttpClient $http */
        $http = self::getContainer()->get(ScriptedHttpClient::class);
        $this->http = $http;
    }

    protected function tearDown(): void
    {
        $this->connection()->rollBack();
        parent::tearDown();
    }

    public function testThePennylaneAdapterIsTheTransmitterOfTheTestApplication(): void
    {
        self::assertInstanceOf(PennylaneInvoiceTransmitter::class, self::getContainer()->get('test.tmonier_einvoice.transmitter'));
    }

    public function testAGeneratedInvoiceIsImportedInPennylaneAndLoggedAsDeposited(): void
    {
        $this->http->enqueue(
            ScriptedHttpClient::json(200, ['has_more' => false, 'next_cursor' => null, 'items' => []]),
            ScriptedHttpClient::json(201, ['id' => 7]),
            ScriptedHttpClient::json(201, ['id' => 42, 'url' => 'u', 'warnings' => []]),
        );

        $this->dispatchGenerated('invoice-1');

        $log = $this->logged('invoice-1');
        self::assertNotNull($log);
        self::assertSame(TransmissionStatus::DEPOSITED, $log->status());
        self::assertStringContainsString('invoice id 42', (string) $log->message());
        self::assertCount(3, $this->http->requests);
    }

    public function testARefusedTokenLeavesTheInvoiceGeneratedAndLogsAnErrorWithoutTheToken(): void
    {
        $this->http->enqueue(ScriptedHttpClient::json(401, ['error' => 'The access token is invalid', 'status' => 401]));

        $this->dispatchGenerated('invoice-2');

        $log = $this->logged('invoice-2');
        self::assertNotNull($log);
        self::assertSame(TransmissionStatus::ERROR, $log->status());
        self::assertStringNotContainsString('test-token-0123456789', (string) $log->message());
        self::assertCount(1, $this->http->requests);
    }

    public function testASendInErrorIsRelaunchedOnceThroughTheHandlerOfTheTransmissionPlugin(): void
    {
        $this->http->enqueue(ScriptedHttpClient::json(503, ['error' => 'down']));
        $this->dispatchGenerated('invoice-3');
        $log = $this->logged('invoice-3');
        self::assertNotNull($log);
        self::assertSame(TransmissionStatus::ERROR, $log->status());

        $this->http->enqueue(
            ScriptedHttpClient::json(200, ['has_more' => false, 'next_cursor' => null, 'items' => [['id' => 7]]]),
            ScriptedHttpClient::json(201, ['id' => 44, 'url' => 'u', 'warnings' => []]),
        );
        $this->relaunchHandler()->relaunch('invoice-3');

        $log = $this->logged('invoice-3');
        self::assertNotNull($log);
        self::assertSame(TransmissionStatus::DEPOSITED, $log->status());
        self::assertStringContainsString('invoice id 44', (string) $log->message());
        self::assertCount(3, $this->http->requests);
        $this->expectException(TransmissionNotRelaunchable::class);
        $this->relaunchHandler()->relaunch('invoice-3');
    }

    private function dispatchGenerated(string $invoiceId): void
    {
        $invoice = $this->createStub(InvoiceInterface::class);
        $invoice->method('id')->willReturn($invoiceId);
        $invoice->method('number')->willReturn('FA-' . $invoiceId);
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = self::getContainer()->get('event_dispatcher');

        $dispatcher->dispatch(new FacturXInvoiceGenerated($invoice, FacturXXml::forCompany(), '%PDF-1.7 test', 'FA-' . $invoiceId . '.pdf'), FacturXInvoiceGenerated::NAME);
    }

    private function relaunchHandler(): RelaunchTransmissionHandler
    {
        /** @var RelaunchTransmissionHandler $handler */
        $handler = self::getContainer()->get('test.tmonier_einvoice.relaunch');

        return $handler;
    }

    private function logged(string $invoiceId): ?InvoiceTransmission
    {
        $entityManager = $this->entityManager();
        $entityManager->clear();

        return $entityManager->find(InvoiceTransmission::class, $invoiceId);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }
}
