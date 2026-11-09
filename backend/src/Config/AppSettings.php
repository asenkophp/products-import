<?php

declare(strict_types=1);

namespace App\Config;

use Psr\Container\ContainerInterface;

/**
 * Типизированный доступ к настройкам из config/settings.php (для PHPStan и автодополнения).
 *
 * @phpstan-type Settings array{
 *     env: string,
 *     debug: bool,
 *     doctrine: array{entity_paths: list<string>, proxy_dir: string, connection: array<string, mixed>},
 *     messenger: array{dsn: string},
 *     import: array{storage_dir: string, max_file_size: int, images_dir: string, images_public_prefix: string, rate_limit: array{limit: int, interval: string}},
 *     auth: array{jwt_secret: string, token_ttl: int, login_rate_limit: int},
 *     cache_dir: string
 * }
 */
final class AppSettings
{
    /** @return Settings */
    public static function from(ContainerInterface $container): array
    {
        /** @var Settings $settings */
        $settings = $container->get('settings');

        return $settings;
    }
}
