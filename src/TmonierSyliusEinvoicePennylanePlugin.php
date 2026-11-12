<?php

declare(strict_types=1);

namespace Tmonier\SyliusEinvoicePennylanePlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tmonier\SyliusEinvoicePennylanePlugin\DependencyInjection\TmonierSyliusEinvoicePennylaneExtension;

final class TmonierSyliusEinvoicePennylanePlugin extends Bundle
{
    use SyliusPluginTrait;

    private ?ExtensionInterface $pennylaneExtension = null;

    /**
     * The configuration key is `tmonier_einvoice_pennylane` (shorter than the one derived from the bundle name),
     * so the extension is returned directly instead of going through the naming convention check of Bundle.
     */
    public function getContainerExtension(): ExtensionInterface
    {
        return $this->pennylaneExtension ??= new TmonierSyliusEinvoicePennylaneExtension();
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
