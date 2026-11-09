<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class UserRepository
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function find(int $id): ?User
    {
        return $this->em->find(User::class, $id);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
    }

    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }
}
