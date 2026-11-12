<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support;

/** The part of a CII (Factur-X) document the adapter reads: the buyer. */
final class FacturXXml
{
    public static function forCompany(string $siren = '732829320', string $name = 'Acme Industries SAS', string $vat = 'FR44732829320'): string
    {
        return self::document(sprintf(
            '<ram:Name>%s</ram:Name><ram:SpecifiedLegalOrganization><ram:ID schemeID="0002">%s</ram:ID></ram:SpecifiedLegalOrganization>'
            . '<ram:PostalTradeAddress><ram:PostcodeCode>75002</ram:PostcodeCode><ram:LineOne>5 rue de la Paix</ram:LineOne><ram:CityName>Paris</ram:CityName><ram:CountryID>FR</ram:CountryID></ram:PostalTradeAddress>'
            . '<ram:URIUniversalCommunication><ram:URIID schemeID="EM">achats@acme.example</ram:URIID></ram:URIUniversalCommunication>'
            . '<ram:SpecifiedTaxRegistration><ram:ID schemeID="VA">%s</ram:ID></ram:SpecifiedTaxRegistration>',
            $name,
            $siren,
            $vat,
        ));
    }

    public static function forIndividual(string $name = 'Jeanne Martin'): string
    {
        return self::document(sprintf(
            '<ram:Name>%s</ram:Name><ram:PostalTradeAddress><ram:PostcodeCode>69003</ram:PostcodeCode><ram:LineOne>12 cours Lafayette</ram:LineOne><ram:CityName>Lyon</ram:CityName><ram:CountryID>FR</ram:CountryID></ram:PostalTradeAddress>',
            $name,
        ));
    }

    public static function withoutBuyer(): string
    {
        return self::document('', false);
    }

    private static function document(string $buyer, bool $withBuyer = true): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">'
            . '<rsm:SupplyChainTradeTransaction><ram:ApplicableHeaderTradeAgreement>'
            . ($withBuyer ? '<ram:BuyerTradeParty>' . $buyer . '</ram:BuyerTradeParty>' : '')
            . '</ram:ApplicableHeaderTradeAgreement></rsm:SupplyChainTradeTransaction></rsm:CrossIndustryInvoice>';
    }
}
