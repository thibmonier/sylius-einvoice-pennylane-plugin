<?php

declare(strict_types=1);

namespace Tmonier\SyliusEinvoicePennylanePlugin\Api;

use Tmonier\SyliusEinvoiceTransmissionPlugin\Status\TransmissionStatus;

/**
 * A call to Pennylane that did not succeed. The message is written for the transmission journal: it never carries
 * the token, the body of the request or the body of the answer, only the HTTP status.
 */
final class PennylaneApiException extends \RuntimeException
{
    private function __construct(string $message, public readonly string $transmissionStatus)
    {
        parent::__construct($message);
    }

    public static function tokenMissing(): self
    {
        return new self('The Pennylane API token is not configured: set tmonier_einvoice_pennylane.api_token, then relaunch the send.', TransmissionStatus::ERROR);
    }

    public static function unauthorized(int $status): self
    {
        return new self(sprintf('Pennylane refused the API token or its scope (HTTP %d): check the token (scopes customers:all and customer_invoices:all), then relaunch the send.', $status), TransmissionStatus::ERROR);
    }

    public static function throttled(?int $retryAfter): self
    {
        $wait = null === $retryAfter ? '' : sprintf(' Retry in %d seconds.', $retryAfter);

        return new self('Pennylane rate limit reached (HTTP 429).' . $wait . ' The send can be relaunched.', TransmissionStatus::ERROR);
    }

    public static function unavailable(?int $status): self
    {
        $detail = null === $status ? 'no answer in time or connection failed' : sprintf('HTTP %d', $status);

        return new self(sprintf('Pennylane is unavailable (%s). The send can be relaunched.', $detail), TransmissionStatus::ERROR);
    }

    public static function refused(int $status): self
    {
        return new self(sprintf('Pennylane refused the request (HTTP %d).', $status), TransmissionStatus::REJECTED);
    }

    public static function unexpectedAnswer(): self
    {
        return new self('Pennylane answered something this plugin does not understand. The send can be relaunched.', TransmissionStatus::ERROR);
    }
}
