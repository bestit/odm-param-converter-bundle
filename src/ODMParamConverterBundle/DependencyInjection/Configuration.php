<?php

namespace BestIt\ODMParamConverterBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 *
 * @author Michel Chowanski <chowanski@bestit-online.de>
 * @package BestIt\ODMParamConverterBundle\DependencyInjection
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Parses the config.
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder();
        $builder->root('best_it_odmparam_converter')
            ->children()
                ->scalarNode('odm_manager')
                    ->info('Service id of odm manager.')
                    ->defaultValue('best_it.commercetools_odm.manager')
                    ->cannotBeEmpty()
                ->end()
            ->end();

        return $builder;
    }
}
