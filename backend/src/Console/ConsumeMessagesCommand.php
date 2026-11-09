<?php

declare(strict_types=1);

namespace App\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;

/**
 * Воркер очереди импорта. Аналог messenger:consume из Symfony для Slim.
 * Останавливается по лимиту памяти/времени — Docker перезапускает контейнер (restart: unless-stopped).
 */
#[AsCommand(name: 'app:worker', description: 'Обработка сообщений из очереди RabbitMQ')]
final class ConsumeMessagesCommand extends Command
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Лимит памяти в мегабайтах', '256')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Лимит времени работы в секундах', '3600');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new ConsoleLogger($output);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMemoryLimitListener((int) $input->getOption('memory-limit') * 1024 * 1024, $logger));
        $dispatcher->addSubscriber(new StopWorkerOnTimeLimitListener((int) $input->getOption('time-limit'), $logger));

        $worker = new Worker(['async' => $this->transport], $this->bus, $dispatcher, $logger);

        // docker stop / restart присылает SIGTERM: дорабатываем текущее сообщение и выходим штатно
        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            foreach ([SIGTERM, SIGINT] as $signal) {
                pcntl_signal($signal, static function () use ($worker, $output): void {
                    $output->writeln('<comment>Получен сигнал остановки, завершаю после текущего сообщения...</comment>');
                    $worker->stop();
                });
            }
        }

        $output->writeln('<info>Воркер запущен, жду сообщения из очереди...</info>');
        $worker->run(['sleep' => 1_000_000]);

        return Command::SUCCESS;
    }
}
