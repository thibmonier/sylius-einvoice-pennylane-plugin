<?php

declare(strict_types=1);

namespace Tmonier\SyliusEinvoicePennylanePlugin\Buyer;

/** Placeholder: the reading of the buyer (BuyerTradeParty) is written once the tests have failed. */
final class FacturXBuyerReader
{
    /** @throws InvalidFacturXDocument */
    public function read(string $xml): Buyer
    {
        return new Buyer('', '', '', '', '');
    }
}
