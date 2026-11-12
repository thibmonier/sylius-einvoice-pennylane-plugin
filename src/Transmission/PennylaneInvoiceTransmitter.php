<?php

declare(strict_types=1);

namespace Tmonier\SyliusEinvoicePennylanePlugin\Transmission;

use Tmonier\SyliusEinvoicePennylanePlugin\Api\PennylaneClient;
use Tmonier\SyliusEinvoicePennylanePlugin\Buyer\FacturXBuyerReader;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Status\TransmissionStatus;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\InvoiceDocument;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\InvoiceTransmitterInterface;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\TransmissionResult;

/** Placeholder: nothing is sent to Pennylane yet. */
final readonly class PennylaneInvoiceTransmitter implements InvoiceTransmitterInterface
{
    public function __construct(
        PennylaneClient $client,
        FacturXBuyerReader $buyers,
    ) {
    }

    public function transmit(InvoiceDocument $document): TransmissionResult
    {
        return new TransmissionResult(TransmissionStatus::NOT_TRANSMITTED, 'Not implemented yet.');
    }
}
