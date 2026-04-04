<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Command;

use App\Shared\Command\CleanupCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CleanupCommand::class)]
final class CleanupCommandTest extends TestCase
{
    public function testExecutesCleanup(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(5);

        $clock = new MockClock('2026-04-04 12:00:00');

        $command = new CleanupCommand($connection, $clock, 90, 30);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Cleanup complete', $tester->getDisplay());
        self::assertStringContainsString('90 days articles', $tester->getDisplay());
    }
}
