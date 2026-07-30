<?php

// ABOUTME: Unit test for CheckDatabaseIsReachableRunner - a round trip answers true, a failure false.
// ABOUTME: An unreachable database is an answer to the question, not an exception, but it is logged.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\System;

use App\Message\Query\System\CheckDatabaseIsReachableQuery;
use App\Message\Query\System\CheckDatabaseIsReachableRunner;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CheckDatabaseIsReachableRunnerTest extends TestCase
{
    public function testACompletedRoundTripIsReachable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('executeQuery')
            ->willReturn(self::createStub(Result::class))
        ;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $runner = new CheckDatabaseIsReachableRunner($connection, $logger);

        self::assertTrue($runner(new CheckDatabaseIsReachableQuery()));
    }

    public function testAFailedRoundTripIsNotReachable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('executeQuery')
            ->willThrowException(new \RuntimeException('the database is not listening'))
        ;

        $runner = new CheckDatabaseIsReachableRunner($connection, self::createStub(LoggerInterface::class));

        self::assertFalse($runner(new CheckDatabaseIsReachableQuery()));
    }

    /**
     * The probe answers with a boolean so a health endpoint can map it to a status code, but the
     * reason the database could not be reached is the only thing that makes the failure actionable -
     * so it has to reach the log rather than being flattened into the false.
     */
    public function testTheFailureReasonIsLogged(): void
    {
        $exception = new \RuntimeException('the database is not listening');
        $connection = self::createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException($exception);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(self::anything(), self::callback(
                static fn (array $context): bool => ($context['exception'] ?? null) === $exception,
            ))
        ;

        $runner = new CheckDatabaseIsReachableRunner($connection, $logger);

        $runner(new CheckDatabaseIsReachableQuery());
    }
}
