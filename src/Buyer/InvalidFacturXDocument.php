<?php

declare(strict_types=1);

namespace Tmonier\SyliusEinvoicePennylanePlugin\Buyer;

final class InvalidFacturXDocument extends \RuntimeException
{
    public static function noBuyer(): self
    {
        return new self('The Factur-X XML does not contain a buyer (BuyerTradeParty).');
    }
}
