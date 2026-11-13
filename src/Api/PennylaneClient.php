<?php

declare(strict_types=1);

namespace Tmonier\SyliusEinvoicePennylanePlugin\Api;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tmonier\SyliusEinvoicePennylanePlugin\Buyer\Buyer;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\InvoiceDocument;

/**
 * The three calls of the Pennylane Company API v2 this plugin needs. One host (from the configuration), one token,
 * no automatic retry: a failed call is reported and the send is relaunched from the administration.
 *
 * @see https://pennylane.readme.io/reference/getcustomers          GET  /api/external/v2/customers (filter on external_reference)
 * @see https://pennylane.readme.io/reference/postcustomer          POST /api/external/v2/customers
 * @see https://pennylane.readme.io/reference/createcustomerinvoiceeinvoiceimport POST /api/external/v2/customer_invoices/e_invoices/imports
 */
final readonly class PennylaneClient
{
    private const CUSTOMERS = '/api/external/v2/customers';

    private const EINVOICE_IMPORTS = '/api/external/v2/customer_invoices/e_invoices/imports';

    public function __construct(
        private HttpClientInterface $http,
        private string $baseUrl,
        #[\SensitiveParameter]
        private ?string $token,
        private float $timeout,
    ) {
    }

    /** @throws PennylaneApiException */
    public function findCustomerId(string $reference): ?int
    {
        $filter = json_encode([['field' => 'external_reference', 'operator' => 'eq', 'value' => $reference]], \JSON_THROW_ON_ERROR);
        $answer = $this->request('GET', self::CUSTOMERS, ['query' => ['filter' => $filter, 'limit' => 1]]);
        $items = $answer['items'] ?? null;
        if (!\is_array($items)) {
            throw PennylaneApiException::unexpectedAnswer();
        }
        if ([] === $items) {
            return null;
        }

        return $this->idOf($items[array_key_first($items)]);
    }

    /** @throws PennylaneApiException */
    public function createCustomer(Buyer $buyer, string $reference): int
    {
        return $this->idOf($this->request('POST', self::CUSTOMERS, ['json' => $this->customerPayload($buyer, $reference)]));
    }

    /**
     * Imports the Factur-X PDF as the invoice of the customer; answers the Pennylane id of the invoice.
     *
     * @throws PennylaneApiException
     */
    public function importInvoice(InvoiceDocument $document, int $customerId): int
    {
        $form = new FormDataPart([
            'file' => new DataPart($document->pdfContent, $document->pdfFilename, 'application/pdf'),
            'invoice_options' => json_encode(['customer_id' => $customerId], \JSON_THROW_ON_ERROR),
        ]);

        return $this->idOf($this->request('POST', self::EINVOICE_IMPORTS, [
            'headers' => $form->getPreparedHeaders()->toArray(),
            'body' => $form->bodyToIterable(),
        ]));
    }

    /** @return array<string, mixed> */
    private function customerPayload(Buyer $buyer, string $reference): array
    {
        $payload = ['external_reference' => $reference, 'billing_address' => [
            'address' => $buyer->address,
            'postal_code' => $buyer->postalCode,
            'city' => $buyer->city,
            'country_alpha2' => $buyer->countryCode,
        ]];
        if (null !== $buyer->email) {
            $payload['emails'] = [$buyer->email];
        }
        if ($buyer->isCompany()) {
            return $payload + array_filter(['customer_type' => 'company', 'name' => $buyer->name, 'reg_no' => $buyer->siren, 'vat_number' => $buyer->vatId]);
        }
        $parts = explode(' ', trim($buyer->name), 2);

        return $payload + ['customer_type' => 'individual', 'first_name' => 2 === \count($parts) ? $parts[0] : '-', 'last_name' => $parts[1] ?? $parts[0]];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>
     */
    private function request(string $method, string $path, array $options): array
    {
        if (null === $this->token || '' === $this->token) {
            throw PennylaneApiException::tokenMissing();
        }

        try {
            $response = $this->http->request($method, rtrim($this->baseUrl, '/') . $path, $options + ['auth_bearer' => $this->token, 'timeout' => $this->timeout, 'headers' => ['Accept' => 'application/json']]);
            $status = $response->getStatusCode();
            if ($status < 300) {
                return $response->toArray(false);
            }
            $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;
        } catch (ExceptionInterface) {
            // The message of the exception is dropped on purpose: it may quote the request.
            throw PennylaneApiException::unavailable(null);
        }

        throw match (true) {
            401 === $status, 403 === $status => PennylaneApiException::unauthorized($status),
            429 === $status => PennylaneApiException::throttled(is_numeric($retryAfter) ? (int) $retryAfter : null),
            $status >= 500 => PennylaneApiException::unavailable($status),
            default => PennylaneApiException::refused($status),
        };
    }

    private function idOf(mixed $answer): int
    {
        if (!\is_array($answer) || !isset($answer['id']) || !\is_int($answer['id'])) {
            throw PennylaneApiException::unexpectedAnswer();
        }

        return $answer['id'];
    }
}
