<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support\FixedInvoiceDocumentProvider;
use Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support\ScriptedHttpClient;
use Tmonier\SyliusEinvoiceTransmissionPlugin\Transmission\RelaunchTransmissionHandler;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * Test application only: the scripted HTTP client, and the services the tests read directly.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->set(ScriptedHttpClient::class)->public();
    $services->set(FixedInvoiceDocumentProvider::class)->public();
    $services->alias('test.tmonier_einvoice.relaunch', RelaunchTransmissionHandler::class)->public();
    $services->alias('test.tmonier_einvoice.transmitter', 'tmonier_einvoice.transmitter')->public();
};
