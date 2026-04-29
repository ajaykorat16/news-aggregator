<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'secret' => '%env(APP_SECRET)%',
        'trusted_proxies' => '%env(TRUSTED_PROXIES)%',
        'trusted_headers' => ['x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto', 'x-forwarded-port', 'x-forwarded-prefix'],
        // Note that the session will be started ONLY if you read or write from it.
        'session' => [
            'enabled' => true,
        ],
        // 'esi' => ['enabled' => true],
        // 'fragments' => ['enabled' => true],
    ]);

    if ($container->env() === 'test') {
        $container->extension('framework', [
            'test' => true,
            'session' => [
                'storage_factory_id' => 'session.storage.factory.mock_file',
            ],
        ]);
    }
};
