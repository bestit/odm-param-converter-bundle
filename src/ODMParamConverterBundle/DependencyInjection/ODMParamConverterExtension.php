<?php

namespace BestIt\ODMParamConverterBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class ODMParamConverterExtension
 *
 * @author Michel Chowanski <chowanski@bestit-online.de>
 * @package BestIt\ODMParamConverterBundle\DependencyInjection
 */
class ODMParamConverterExtension extends Extension
{
    /**
     * @inheritdoc
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('best_it_odmparam_converter.odm_manager', $config['odm_manager']);
    }
}
