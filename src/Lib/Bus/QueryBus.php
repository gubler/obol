<?php

declare(strict_types=1);

namespace App\Lib\Bus;

use Doctrine\ORM\NoResultException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Wrapper for Messenger QueryBus.
 */
final readonly class QueryBus
{
    public function __construct(
        #[Autowire(service: 'query.bus')]
        private MessageBusInterface $queryBus,
    ) {
    }

    public function query(object $query): mixed
    {
        /** @var HandledStamp|null $stamp */
        $stamp = $this->queryBus
            ->dispatch(message: $query)
            ->last(stampFqcn: HandledStamp::class)
        ;

        if (null === $stamp) {
            throw new NoResultException();
        }

        return $stamp->getResult();
    }
}
