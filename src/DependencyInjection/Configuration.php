<?php

declare(strict_types=1);

namespace Tmonier\SyliusEinvoicePennylanePlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const DEFAULT_BASE_URL = 'https://app.pennylane.com';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tmonier_einvoice_pennylane');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('api_token')
                    ->info('Pennylane API token (scopes customers:all and customer_invoices:all). Give it as an environment variable: %env(PENNYLANE_API_TOKEN)%. Without a token every send ends in error.')
                    ->defaultNull()
                ->end()
                ->scalarNode('base_url')
                    ->info('Host of the Pennylane API, changed only in the configuration (for example for the sandbox), never from a user input.')
                    ->defaultValue(self::DEFAULT_BASE_URL)
                    ->cannotBeEmpty()
                ->end()
                ->floatNode('timeout')
                    ->info('Seconds to wait for an answer of Pennylane, per request.')
                    ->defaultValue(10.0)
                    ->min(1.0)
                ->end()
                ->scalarNode('http_client')
                    ->info('Id of the Symfony HTTP client service used for the calls.')
                    ->defaultValue('http_client')
                    ->cannotBeEmpty()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
