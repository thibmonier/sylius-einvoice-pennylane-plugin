<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support;

use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\InvoiceDocument;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\InvoiceDocumentProviderInterface;

/** Gives back the document of any invoice (the journal does not keep it): the relaunch reads it here. */
final class FixedInvoiceDocumentProvider implements InvoiceDocumentProviderInterface
{
    public function provide(string $invoiceId): InvoiceDocument
    {
        return new InvoiceDocument($invoiceId, 'FA-' . $invoiceId, FacturXXml::forCompany(), '%PDF-1.7 test', 'FA-' . $invoiceId . '.pdf');
    }
}
