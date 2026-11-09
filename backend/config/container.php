<?php

declare(strict_types=1);

use App\Config\AppSettings;
use App\Console\ConsumeMessagesCommand;
use App\Console\CreateUserCommand;
use App\Console\LoadFixturesCommand;
use App\Controller\HealthController;
use App\Doctrine\ResettableEntityManager;
use App\Message\ImportProductsMessage;
use App\MessageHandler\ImportProductsHandler;
use App\Middleware\RateLimitMiddleware;
use App\Service\Auth\TokenService;
use App\Service\Import\ImageDownloader;
use App\Service\Import\ImageDownloaderInterface;
use App\Service\Import\ImportFileValidator;
use App\Service\Import\ImportLauncher;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransportFactory;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

use function DI\autowire;
use function DI\create;
use function DI\get;

/** Лимит «N запросов в минуту с одного IP» (sliding window), состояние — в файловом кеше. */
$rateLimiter = static fn(string $id, int $limit, string $cacheDir): RateLimiterFactory => new RateLimiterFactory(
    ['id' => $id, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 minute'],
    new CacheStorage(new FilesystemAdapter('rate_limiter', 0, $cacheDir)),
);

return [
    'settings' => require __DIR__ . '/settings.php',

    // ---------- Doctrine ----------
    // EntityManager обёрнут в «сбрасываемый» декоратор: после ошибки БД Doctrine закрывает EM,
    // и без пересоздания воркер не смог бы обработать оставшиеся строки импорта.
    ResettableEntityManager::class => static function (ContainerInterface $c): ResettableEntityManager {
        $settings = AppSettings::from($c);
        $doctrine = $settings['doctrine'];
        $isDev    = $settings['env'] === 'dev';

        return new ResettableEntityManager(static function () use ($doctrine, $isDev): EntityManagerInterface {
            $config = ORMSetup::createAttributeMetadataConfiguration(
                paths: $doctrine['entity_paths'],
                isDevMode: $isDev,
                proxyDir: $doctrine['proxy_dir'],
            );


            return new EntityManager(DriverManager::getConnection($doctrine['connection'], $config), $config);
        });
    },
    EntityManagerInterface::class => get(ResettableEntityManager::class),

    // ---------- HTTP ----------
    ResponseFactoryInterface::class => create(ResponseFactory::class),
    ClientInterface::class          => create(Client::class),

    // ---------- Messenger + RabbitMQ ----------
    'messenger.transport.async' => static function (ContainerInterface $c): TransportInterface {
        $settings = AppSettings::from($c);

        // amqp://… — RabbitMQ, in-memory:// — для тестов
        $factory = new TransportFactory([new AmqpTransportFactory(), new InMemoryTransportFactory()]);

        return $factory->createTransport($settings['messenger']['dsn'], [], new PhpSerializer());
    },

    MessageBusInterface::class => static function (ContainerInterface $c): MessageBusInterface {
        // куда отправлять сообщения (из HTTP-запроса уходят в RabbitMQ)
        $senders = new SendersLocator([
            ImportProductsMessage::class => ['messenger.transport.async'],
        ], $c);

        // кто обрабатывает сообщения (в воркере); обработчик создаётся лениво
        $handlers = new HandlersLocator([
            ImportProductsMessage::class => [
                static function (ImportProductsMessage $message) use ($c): void {
                    /** @var ImportProductsHandler $handler */
                    $handler = $c->get(ImportProductsHandler::class);
                    $handler($message);
                },
            ],
        ]);

        return new MessageBus([
            new SendMessageMiddleware($senders),
            new HandleMessageMiddleware($handlers),
        ]);
    },

    ConsumeMessagesCommand::class => autowire()
        ->constructorParameter('transport', get('messenger.transport.async')),

    HealthController::class => autowire()
        ->constructorParameter('transport', get('messenger.transport.async')),

    LoadFixturesCommand::class => static function (ContainerInterface $c): LoadFixturesCommand {
        $settings = AppSettings::from($c);

        return new LoadFixturesCommand(
            $c->get(EntityManagerInterface::class),
            $settings['import']['images_dir'],
            $settings['import']['images_public_prefix'],
        );
    },

    // ---------- Импорт ----------
    ImportFileValidator::class => static function (ContainerInterface $c): ImportFileValidator {
        $settings = AppSettings::from($c);

        return new ImportFileValidator($settings['import']['max_file_size']);
    },

    ImportLauncher::class => static function (ContainerInterface $c): ImportLauncher {
        $settings = AppSettings::from($c);

        return new ImportLauncher(
            $c->get(App\Repository\ImportJobRepository::class),
            $c->get(MessageBusInterface::class),
            $c->get(ImportFileValidator::class),
            $settings['import']['storage_dir'],
        );
    },

    ImageDownloader::class => static function (ContainerInterface $c): ImageDownloader {
        $settings = AppSettings::from($c);

        return new ImageDownloader(
            $c->get(ClientInterface::class),
            $settings['import']['images_dir'],
            $settings['import']['images_public_prefix'],
        );
    },

    ImageDownloaderInterface::class => get(ImageDownloader::class),

    // ---------- Rate limiting: загрузка файлов и вход ----------
    RateLimitMiddleware::class => static function (ContainerInterface $c) use ($rateLimiter): RateLimitMiddleware {
        $settings = AppSettings::from($c);

        return new RateLimitMiddleware(
            $rateLimiter('import', $settings['import']['rate_limit']['limit'], $settings['cache_dir']),
            $c->get(ResponseFactoryInterface::class),
        );
    },

    'middleware.rate_limit.login' => static function (ContainerInterface $c) use ($rateLimiter): RateLimitMiddleware {
        $settings = AppSettings::from($c);

        return new RateLimitMiddleware(
            $rateLimiter('login', $settings['auth']['login_rate_limit'], $settings['cache_dir']),
            $c->get(ResponseFactoryInterface::class),
        );
    },

    // ---------- Аутентификация ----------
    TokenService::class => static function (ContainerInterface $c): TokenService {
        $settings = AppSettings::from($c);

        return new TokenService($settings['auth']['jwt_secret'], $settings['auth']['token_ttl']);
    },

    CreateUserCommand::class => autowire(),
];
