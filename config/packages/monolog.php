<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    // Deprecations are logged in the dedicated "deprecation" channel when it exists
    $container->extension('monolog', [
        'channels' => ['deprecation'],
    ]);

    if ($container->env() === 'dev') {
        $container->extension('monolog', [
            'handlers' => [
                'main' => [
                    'type' => 'stream',
                    'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                    'level' => 'debug',
                    'channels' => [
                        'elements' => ['!event'],
                    ],
                ],
                'console' => [
                    'type' => 'console',
                    'process_psr_3_messages' => false,
                    'channels' => [
                        'elements' => ['!event', '!doctrine', '!console'],
                    ],
                ],
            ],
        ]);
    }

    if ($container->env() === 'test') {
        $container->extension('monolog', [
            'handlers' => [
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'error',
                    'handler' => 'nested',
                    'excluded_http_codes' => [404, 405],
                    'channels' => [
                        'elements' => ['!event'],
                    ],
                ],
                'nested' => [
                    'type' => 'stream',
                    'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                    'level' => 'debug',
                ],
            ],
        ]);
    }

    if ($container->env() === 'prod') {
        $container->extension('monolog', [
            'handlers' => [
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'error',
                    'handler' => 'nested',
                    'excluded_http_codes' => [404, 405],
                    'channels' => [
                        'elements' => ['!deprecation'],
                    ],
                    'buffer_size' => 50,
                ],
                'nested' => [
                    'type' => 'stream',
                    'path' => 'php://stderr',
                    'level' => 'debug',
                    'formatter' => 'monolog.formatter.json',
                ],
                'console' => [
                    'type' => 'console',
                    'process_psr_3_messages' => false,
                    'channels' => [
                        'elements' => ['!event', '!doctrine'],
                    ],
                ],
                'deprecation' => [
                    'type' => 'stream',
                    'channels' => [
                        'elements' => ['deprecation'],
                    ],
                    'path' => 'php://stderr',
                    'formatter' => 'monolog.formatter.json',
                ],
            ],
        ]);
    }
};
