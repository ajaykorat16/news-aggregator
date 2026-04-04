<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Default configuration for services in this file
    $services->defaults()
        ->autowire(true)      // Automatically injects dependencies in your services.
        ->autoconfigure(true); // Automatically registers your services as commands, event subscribers, etc.

    // Makes classes in src/ available to be used as services.
    // This creates a service per class whose id is the fully-qualified class name.
    $services->load('App\\', '../src/')
        ->exclude([
            '../src/Entity/',
            '../src/Kernel.php',
        ]);

    // Add more service definitions when explicit configuration is needed.
    // Note that last definitions always *replace* previous ones.
};
