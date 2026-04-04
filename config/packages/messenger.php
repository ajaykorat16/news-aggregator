<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'messenger' => [
            // Uncomment to send failed messages to this transport for later handling:
            // 'failure_transport' => 'failed',
            'transports' => [
                'async' => [
                    'dsn' => 'doctrine://default',
                    'retry_strategy' => [
                        'max_retries' => 3,
                        'multiplier' => 2,
                        'delay' => 1000,
                        'max_delay' => 0,
                    ],
                ],
                'scheduler_fetch' => 'symfony://scheduler_fetch',
                // 'failed' => ['dsn' => 'doctrine://default?queue_name=failed'],
            ],
            // Route messages to transports:
            // 'routing' => ['App\Message\YourMessage' => 'async'],
        ],
    ]);
};
