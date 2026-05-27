<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Service;

use Maispace\MaiConsent\Domain\Repository\ConsentLogRepository;

/**
 * Enforces a retention window on the consent log: entries older than the
 * configured number of days are physically removed. Wraps the repository so
 * the cutoff calculation stays in one testable place and can be reused by the
 * CLI command and the scheduler.
 */
final class ConsentLogRetentionService
{
    /**
     * Default retention window in days. Consent records double as proof of
     * consent, so the default keeps a full year before erasure.
     */
    public const int DEFAULT_RETENTION_DAYS = 365;

    private const int SECONDS_PER_DAY = 86400;

    public function __construct(
        private readonly ConsentLogRepository $consentLogRepository,
    ) {}

    /**
     * Deletes every consent log entry older than the retention window.
     *
     * @param int $retentionDays Entries created more than this many days ago are erased.
     * @return int Number of removed rows.
     */
    public function purgeExpired(int $retentionDays): int
    {
        return $this->consentLogRepository->deleteOlderThan($this->calculateCutoff($retentionDays));
    }

    /**
     * Returns the Unix timestamp before which entries are considered expired.
     * The retention window is clamped to at least one day so a misconfigured
     * value can never wipe freshly written entries.
     *
     * @param int|null $now Reference time; defaults to the current time. Used for deterministic tests.
     */
    public function calculateCutoff(int $retentionDays, ?int $now = null): int
    {
        $retentionDays = max(1, $retentionDays);
        $now ??= time();

        return $now - ($retentionDays * self::SECONDS_PER_DAY);
    }
}
