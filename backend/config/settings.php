<?php

declare(strict_types=1);

$env = static fn(string $name, string $default = ''): string => (string) (getenv($name) ?: $default);

return [
    'env'   => $env('APP_ENV', 'prod'),
    'debug' => $env('APP_DEBUG', '0') === '1',

    'doctrine' => [
        'entity_paths' => [__DIR__ . '/../src/Entity'],
        'proxy_dir'    => __DIR__ . '/../var/doctrine/proxies',
        'connection'   => [
            'driver'        => 'pdo_pgsql',
            'host'          => $env('DB_HOST', 'db'),
            'port'          => (int) $env('DB_PORT', '5432'),
            'dbname'        => $env('DB_NAME', 'products'),
            'user'          => $env('DB_USER', 'app'),
            'password'      => $env('DB_PASSWORD'),
            'charset'       => 'utf8',
            'serverVersion' => '16',
        ],
    ],

    'messenger' => [
        'dsn' => $env('MESSENGER_TRANSPORT_DSN'),
    ],

    'import' => [
        // куда сохраняются загруженные файлы до обработки воркером
        'storage_dir'   => __DIR__ . '/../var/imports',
        'max_file_size' => 10 * 1024 * 1024,
        // куда скачиваются картинки товаров (отдаются nginx как статика)
        'images_dir'           => __DIR__ . '/../public/uploads/products',
        'images_public_prefix' => '/uploads/products',
        // не более N загрузок файла за интервал с одного IP
        'rate_limit' => ['limit' => (int) $env('IMPORT_RATE_LIMIT', '5'), 'interval' => '1 minute'],
    ],

    'auth' => [
        // секрет подписи JWT (HS256), не короче 32 символов
        'jwt_secret'       => $env('JWT_SECRET'),
        'token_ttl'        => (int) $env('JWT_TTL', '28800'),
        'login_rate_limit' => (int) $env('LOGIN_RATE_LIMIT', '10'),
    ],

    'cache_dir' => __DIR__ . '/../var/cache',
];
