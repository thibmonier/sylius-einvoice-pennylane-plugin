<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusEinvoicePennylanePlugin\Unit\Buyer;

use PHPUnit\Framework\TestCase;
use Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support\FacturXXml;
use Tmonier\SyliusEinvoicePennylanePlugin\Buyer\FacturXBuyerReader;
use Tmonier\SyliusEinvoicePennylanePlugin\Buyer\InvalidFacturXDocument;

final class FacturXBuyerReaderTest extends TestCase
{
    public function testItReadsACompanyBuyerWithItsIdentifiersAndAddress(): void
    {
        $buyer = (new FacturXBuyerReader())->read(FacturXXml::forCompany());

        self::assertSame('Acme Industries SAS', $buyer->name);
        self::assertSame('732829320', $buyer->siren);
        self::assertSame('FR44732829320', $buyer->vatId);
        self::assertSame('achats@acme.example', $buyer->email);
        self::assertSame('5 rue de la Paix', $buyer->address);
        self::assertSame('75002', $buyer->postalCode);
        self::assertSame('Paris', $buyer->city);
        self::assertSame('FR', $buyer->countryCode);
        self::assertTrue($buyer->isCompany());
    }

    public function testItReadsAnIndividualBuyerWithoutIdentifiers(): void
    {
        $buyer = (new FacturXBuyerReader())->read(FacturXXml::forIndividual());

        self::assertSame('Jeanne Martin', $buyer->name);
        self::assertNull($buyer->siren);
        self::assertFalse($buyer->isCompany());
    }

    public function testTheReferenceOfACompanyIsItsSiren(): void
    {
        $buyer = (new FacturXBuyerReader())->read(FacturXXml::forCompany('732829320'));

        self::assertSame('siren-732829320', $buyer->reference());
    }

    public function testTheReferenceOfAnIndividualIsStableAndDiffersFromAnotherIndividual(): void
    {
        $reader = new FacturXBuyerReader();
        $jeanne = $reader->read(FacturXXml::forIndividual('Jeanne Martin'))->reference();

        self::assertMatchesRegularExpression('/^buyer-[0-9a-f]{32}$/', $jeanne);
        self::assertSame($jeanne, $reader->read(FacturXXml::forIndividual('JEANNE MARTIN '))->reference());
        self::assertNotSame($jeanne, $reader->read(FacturXXml::forIndividual('Paul Durand'))->reference());
    }

    public function testADocumentWithoutBuyerIsRefused(): void
    {
        $this->expectException(InvalidFacturXDocument::class);

        (new FacturXBuyerReader())->read(FacturXXml::withoutBuyer());
    }

    public function testAnInvalidXmlIsRefused(): void
    {
        $this->expectException(InvalidFacturXDocument::class);

        (new FacturXBuyerReader())->read('not xml');
    }
}
