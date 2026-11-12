<?php

declare(strict_types=1);

namespace Tmonier\SyliusEinvoicePennylanePlugin\Api;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tmonier\SyliusEinvoicePennylanePlugin\Buyer\Buyer;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\InvoiceDocument;

/** Placeholder: the calls to the Pennylane Company API v2 are written once the tests have failed. */
final class PennylaneClient
{
    public function __construct(
        HttpClientInterface $http,
        string $baseUrl,
        #[\SensitiveParameter]
        ?string $token,
        float $timeout,
    ) {
    }

    /** @throws PennylaneApiException */
    public function findCustomerId(string $reference): ?int
    {
        return null;
    }

    /** @throws PennylaneApiException */
    public function createCustomer(Buyer $buyer, string $reference): int
    {
        return 0;
    }

    /** @throws PennylaneApiException */
    public function importInvoice(InvoiceDocument $document, int $customerId): int
    {
        return 0;
    }
}
