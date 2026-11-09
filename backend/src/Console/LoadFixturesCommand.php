<?php

declare(strict_types=1);

namespace App\Console;

use App\DataFixtures\PlaceholderImageGenerator;
use App\DataFixtures\ProductAttributeFixtures;
use App\DataFixtures\ProductFixtures;
use App\DataFixtures\ProductImageFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:fixtures:load', description: 'Загрузить тестовые данные во все три таблицы (по умолчанию очищает БД)')]
final class LoadFixturesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $imagesDir,
        private readonly string $imagesPublicPrefix,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('append', null, InputOption::VALUE_NONE, 'Не очищать таблицы перед загрузкой');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $append = (bool) $input->getOption('append');

        if (!$append && !$io->confirm('Все товары и задачи импорта будут удалены (пользователи сохранятся). Продолжить?', false)) {
            $io->warning('Отменено');

            return Command::SUCCESS;
        }

        $loader = new Loader();
        $loader->addFixture(new ProductFixtures());
        $loader->addFixture(new ProductAttributeFixtures());
        $loader->addFixture(new ProductImageFixtures(new PlaceholderImageGenerator($this->imagesDir, $this->imagesPublicPrefix)));

        $executor = new ORMExecutor($this->em, new ORMPurger($this->em, ['users']));
        $executor->execute($loader->getFixtures(), $append);

        $io->success(sprintf('Загружено товаров: %d (с атрибутами и картинками)', ProductFixtures::COUNT));

        return Command::SUCCESS;
    }
}
