<?php
// src/Kernel.php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function configureContainer(): void
    {
        $container = $this->container;
        $confDir = $this->getProjectDir().'/config';

        $container->import($confDir.'/{packages}/*.yaml');
        $container->import($confDir.'/{packages}/'.$this->environment.'/*.yaml');

        if (is_file($confDir.'/services.yaml')) {
            $container->import($confDir.'/services.yaml');
            $container->import($confDir.'/{services}_'.$this->environment.'.yaml');
        }
    }

    protected function configureRoutes(): void
    {
        $confDir = $this->getProjectDir().'/config';

        if (is_file($confDir.'/routes.yaml')) {
            $this->import($confDir.'/routes.yaml');
        }

        if (is_file($confDir.'/routes/'.$this->environment.'.yaml')) {
            $this->import($confDir.'/routes/'.$this->environment.'.yaml');
        }
    }
}
