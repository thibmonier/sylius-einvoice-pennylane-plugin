<?php

declare(strict_types=1);

namespace Tmonier\SyliusEinvoicePennylanePlugin\Transmission;

use Tmonier\SyliusEinvoicePennylanePlugin\Api\PennylaneApiException;
use Tmonier\SyliusEinvoicePennylanePlugin\Api\PennylaneClient;
use Tmonier\SyliusEinvoicePennylanePlugin\Buyer\FacturXBuyerReader;
use Tmonier\SyliusEinvoicePennylanePlugin\Buyer\InvalidFacturXDocument;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Status\TransmissionStatus;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\InvoiceDocument;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\InvoiceTransmitterInterface;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\TransmissionResult;

/** Finds (or creates) the customer in Pennylane, then imports the Factur-X invoice for it. */
final readonly class PennylaneInvoiceTransmitter implements InvoiceTransmitterInterface
{
    public function __construct(
        private PennylaneClient $client,
        private FacturXBuyerReader $buyers,
    ) {
    }

    public function transmit(InvoiceDocument $document): TransmissionResult
    {
        try {
            $buyer = $this->buyers->read($document->xml);
            $reference = $buyer->reference();
            $customerId = $this->client->findCustomerId($reference) ?? $this->client->createCustomer($buyer, $reference);
            $invoiceId = $this->client->importInvoice($document, $customerId);
        } catch (PennylaneApiException $exception) {
            return new TransmissionResult($exception->transmissionStatus, $exception->getMessage());
        } catch (InvalidFacturXDocument $exception) {
            return new TransmissionResult(TransmissionStatus::ERROR, $exception->getMessage());
        }

        return new TransmissionResult(TransmissionStatus::DEPOSITED, sprintf('Imported in Pennylane: invoice id %d (customer id %d).', $invoiceId, $customerId));
    }
}
