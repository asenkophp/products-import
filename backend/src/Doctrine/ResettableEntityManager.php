<?php

declare(strict_types=1);

namespace App\Doctrine;

use Closure;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Декоратор EntityManager, который можно пересоздать после ошибки.
 *
 * Doctrine закрывает EntityManager при любой ошибке во время flush/транзакции.
 * В долгоживущем воркере это означало бы, что одна «плохая» строка импорта
 * ломает обработку всех следующих. Репозитории получают этот декоратор,
 * поэтому после resetIfClosed() они автоматически работают с новым EM.
 */
final class ResettableEntityManager extends EntityManagerDecorator
{
    /** @var Closure(): EntityManagerInterface */
    private readonly Closure $factory;

    /** @param Closure(): EntityManagerInterface $factory */
    public function __construct(Closure $factory)
    {
        $this->factory = $factory;
        parent::__construct($factory());
    }

    public function resetIfClosed(): void
    {
        if ($this->wrapped->isOpen()) {
            return;
        }

        $this->wrapped = ($this->factory)();
    }
}
