<?php

namespace App;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;



class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles()
    {
        return [
            new FrameworkBundle(),
        ];
    }
    protected function configureRoutes(RoutingConfigurator $routes)
    {
        $configDir = $this->getConfigDir();
        $routes->import($configDir.'/routes.yaml');

    }
    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder)
    {
        $configDir = $this->getConfigDir();

        $builder->loadFromExtension('framework', [
            'secret' => 'gheorghe_pavel_dev',
            'http_cache' => true
        ]);
        $container->import($configDir.'/services.yaml');

    }
}