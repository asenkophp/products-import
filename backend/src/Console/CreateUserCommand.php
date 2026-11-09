<?php

declare(strict_types=1);

namespace App\Console;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:create', description: 'Создать пользователя или сменить пароль существующему')]
final class CreateUserCommand extends Command
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(private readonly UserRepository $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::OPTIONAL, 'Логин (по умолчанию ADMIN_USERNAME из .env)')
            ->addArgument('password', InputArgument::OPTIONAL, 'Пароль (по умолчанию ADMIN_PASSWORD из .env)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $username = self::argumentOrEnv($input->getArgument('username'), 'ADMIN_USERNAME');
        $password = self::argumentOrEnv($input->getArgument('password'), 'ADMIN_PASSWORD');

        if ($username === '' || mb_strlen($username) > 100) {
            $io->error('Логин обязателен и должен быть не длиннее 100 символов');

            return Command::INVALID;
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $io->error(sprintf('Пароль должен быть не короче %d символов', self::MIN_PASSWORD_LENGTH));

            return Command::INVALID;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $user = $this->users->findByUsername($username);

        if ($user === null) {
            $this->users->save(new User($username, $hash));
            $io->success(sprintf('Пользователь «%s» создан', $username));
        } else {
            $user->changePasswordHash($hash);
            $this->users->save($user);
            $io->success(sprintf('Пароль пользователя «%s» обновлён', $username));
        }

        return Command::SUCCESS;
    }

    private static function argumentOrEnv(mixed $argument, string $env): string
    {
        if (is_string($argument) && $argument !== '') {
            return trim($argument);
        }

        return trim((string) getenv($env));
    }
}
