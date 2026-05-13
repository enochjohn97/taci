<?php
// src/Kernel.php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);

        // Validate critical environment variables early to avoid hidden defaults
        $required = [
            'APP_SECRET',
            'APP_URL',
            'DATABASE_URL',
            'ALLOWED_HOSTS',
            'MERCURE_PUBLIC_URL',
        ];

        $missing = [];
        foreach ($required as $key) {
            if (empty($_ENV[$key]) && getenv($key) === false) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException('Missing required environment variables: ' . implode(', ', $missing));
        }
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $confDir = $this->getProjectDir().'/config';

        $container->import($confDir.'/{packages}/*.yaml');
        $container->import($confDir.'/{packages}/'.$this->environment.'/*.yaml');

        if (is_file($confDir.'/services.yaml')) {
            $container->import($confDir.'/services.yaml');
            $container->import($confDir.'/{services}_'.$this->environment.'.yaml');
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $confDir = $this->getProjectDir().'/config';

        if (is_file($confDir.'/routes.yaml')) {
            $routes->import($confDir.'/routes.yaml');
        }

        if (is_dir($confDir.'/routes/'.$this->environment)) {
            $routes->import($confDir.'/routes/'.$this->environment.'/*.yaml');
        }
    }
}
