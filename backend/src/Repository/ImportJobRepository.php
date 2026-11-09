<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportJob;
use Doctrine\ORM\EntityManagerInterface;

final class ImportJobRepository
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function find(int $id): ?ImportJob
    {
        return $this->em->find(ImportJob::class, $id);
    }

    public function save(ImportJob $job, bool $flush = true): void
    {
        $this->em->persist($job);
        if ($flush) {
            $this->em->flush();
        }
    }
}
