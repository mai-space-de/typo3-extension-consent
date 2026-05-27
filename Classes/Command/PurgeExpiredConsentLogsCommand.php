<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Command;

use Maispace\MaiConsent\Service\ConsentLogRetentionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'consent:purge-expired-logs',
    description: 'Erase consent log entries older than the configured retention period (GDPR cleanup)',
)]
final class PurgeExpiredConsentLogsCommand extends Command
{
    public function __construct(
        private readonly ConsentLogRetentionService $retentionService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            'd',
            InputOption::VALUE_REQUIRED,
            'Retention period in days. Consent log entries older than this are permanently deleted.',
            (string) ConsentLogRetentionService::DEFAULT_RETENTION_DAYS,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        if ($days < 1) {
            $io->error('The --days option must be a positive integer.');

            return Command::INVALID;
        }

        $deleted = $this->retentionService->purgeExpired($days);

        $io->success(sprintf(
            'Deleted %d expired consent log %s (retention: %d %s).',
            $deleted,
            $deleted === 1 ? 'entry' : 'entries',
            $days,
            $days === 1 ? 'day' : 'days',
        ));

        return Command::SUCCESS;
    }
}
