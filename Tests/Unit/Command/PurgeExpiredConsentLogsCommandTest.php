<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Tests\Unit\Command;

use Maispace\MaiConsent\Command\PurgeExpiredConsentLogsCommand;
use Maispace\MaiConsent\Domain\Repository\ConsentLogRepository;
use Maispace\MaiConsent\Service\ConsentLogRetentionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class PurgeExpiredConsentLogsCommandTest extends TestCase
{
    private const int SECONDS_PER_DAY = 86400;

    public function testExecuteUsesDefaultRetentionWindowWhenNoOptionGiven(): void
    {
        $repository = $this->createMock(ConsentLogRepository::class);
        $repository->expects(self::once())
            ->method('deleteOlderThan')
            ->with($this->cutoffMatcher(ConsentLogRetentionService::DEFAULT_RETENTION_DAYS))
            ->willReturn(3);

        $tester = $this->createTester($repository);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Deleted 3 expired consent log entries', $tester->getDisplay());
        self::assertStringContainsString(
            sprintf('retention: %d days', ConsentLogRetentionService::DEFAULT_RETENTION_DAYS),
            $tester->getDisplay(),
        );
    }

    public function testExecutePassesCustomDaysOptionToService(): void
    {
        $repository = $this->createMock(ConsentLogRepository::class);
        $repository->expects(self::once())
            ->method('deleteOlderThan')
            ->with($this->cutoffMatcher(30))
            ->willReturn(1);

        $tester = $this->createTester($repository);
        $exitCode = $tester->execute(['--days' => '30']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Deleted 1 expired consent log entry', $tester->getDisplay());
        self::assertStringContainsString('retention: 30 days', $tester->getDisplay());
    }

    public function testExecuteRejectsNonPositiveDaysWithoutTouchingRepository(): void
    {
        $repository = $this->createMock(ConsentLogRepository::class);
        $repository->expects(self::never())->method('deleteOlderThan');

        $tester = $this->createTester($repository);
        $exitCode = $tester->execute(['--days' => '0']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('must be a positive integer', $tester->getDisplay());
    }

    public function testCommandIsRegisteredUnderExpectedName(): void
    {
        $command = new PurgeExpiredConsentLogsCommand(
            new ConsentLogRetentionService($this->createMock(ConsentLogRepository::class)),
        );

        self::assertSame('consent:purge-expired-logs', $command->getName());
    }

    private function createTester(ConsentLogRepository $repository): CommandTester
    {
        $command = new PurgeExpiredConsentLogsCommand(new ConsentLogRetentionService($repository));

        return new CommandTester($command);
    }

    private function cutoffMatcher(int $retentionDays): \PHPUnit\Framework\Constraint\Callback
    {
        return self::callback(function (int $cutoff) use ($retentionDays): bool {
            $expected = time() - ($retentionDays * self::SECONDS_PER_DAY);

            return $cutoff <= $expected && $cutoff >= $expected - 5;
        });
    }
}
