<?php

namespace Gdnacho\Poob\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class PoobExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $locator = new FileLocator(__DIR__.'/../Resources/config'); // implements FileLocatorInterface
        $loader = new YamlFileLoader($container, $locator);
        $loader->load('services.yaml');
    }
}
