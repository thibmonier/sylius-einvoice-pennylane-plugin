<?php

declare(strict_types=1);

namespace Tmonier\SyliusEinvoicePennylanePlugin\Buyer;

/** Reads the buyer (BuyerTradeParty) of the CII XML of a Factur-X invoice. */
final class FacturXBuyerReader
{
    /** @throws InvalidFacturXDocument */
    public function read(string $xml): Buyer
    {
        $document = new \DOMDocument();
        if ('' === $xml || !$document->loadXML($xml, \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING)) {
            throw InvalidFacturXDocument::noBuyer();
        }
        $xpath = new \DOMXPath($document);
        $parties = $xpath->query("//*[local-name()='ApplicableHeaderTradeAgreement']/*[local-name()='BuyerTradeParty']");
        $party = false === $parties ? null : $parties->item(0);
        if (!$party instanceof \DOMNode) {
            throw InvalidFacturXDocument::noBuyer();
        }
        $text = static function (string $path) use ($xpath, $party): ?string {
            $nodes = $xpath->query($path, $party);
            $node = false === $nodes ? null : $nodes->item(0);
            $value = $node instanceof \DOMNode ? trim($node->textContent) : '';

            return '' === $value ? null : $value;
        };
        $email = $text("*[local-name()='URIUniversalCommunication']/*[local-name()='URIID']");

        return new Buyer(
            $text("*[local-name()='Name']") ?? '',
            $text("*[local-name()='PostalTradeAddress']/*[local-name()='LineOne']") ?? '',
            $text("*[local-name()='PostalTradeAddress']/*[local-name()='PostcodeCode']") ?? '',
            $text("*[local-name()='PostalTradeAddress']/*[local-name()='CityName']") ?? '',
            $text("*[local-name()='PostalTradeAddress']/*[local-name()='CountryID']") ?? '',
            $text("*[local-name()='SpecifiedLegalOrganization']/*[local-name()='ID']"),
            $text("*[local-name()='SpecifiedTaxRegistration']/*[local-name()='ID']"),
            null !== $email && str_contains($email, '@') ? $email : null,
        );
    }
}
