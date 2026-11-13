<?php

declare(strict_types=1);

namespace Tmonier\SyliusEinvoicePennylanePlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class TmonierSyliusEinvoicePennylaneExtension extends Extension
{
    public function getAlias(): string
    {
        return 'tmonier_einvoice_pennylane';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{api_token: ?string, base_url: string, timeout: float, http_client: string} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('tmonier_einvoice_pennylane.api_token', $config['api_token']);
        $container->setParameter('tmonier_einvoice_pennylane.base_url', $config['base_url']);
        $container->setParameter('tmonier_einvoice_pennylane.timeout', $config['timeout']);
        $container->setAlias('tmonier_einvoice_pennylane.http_client', $config['http_client']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');
    }
}
