<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusEinvoicePennylanePlugin\Api\PennylaneClient;
use Tmonier\SyliusEinvoicePennylanePlugin\Buyer\FacturXBuyerReader;
use Tmonier\SyliusEinvoicePennylanePlugin\Transmission\PennylaneInvoiceTransmitter;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->set(PennylaneClient::class)
        ->args([
            service('tmonier_einvoice_pennylane.http_client'),
            param('tmonier_einvoice_pennylane.base_url'),
            param('tmonier_einvoice_pennylane.api_token'),
            param('tmonier_einvoice_pennylane.timeout'),
        ])
    ;
    $services->set(FacturXBuyerReader::class);
    $services
        ->set(PennylaneInvoiceTransmitter::class)
        ->args([service(PennylaneClient::class), service(FacturXBuyerReader::class)])
    ;
};
