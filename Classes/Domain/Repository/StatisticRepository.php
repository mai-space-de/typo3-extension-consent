<?php

declare(strict_types = 1);

namespace Maispace\MaiConsent\Domain\Repository;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;

class StatisticRepository
{
    private const TABLE = 'tx_maiconsent_statistic';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    public function record(int $categoryUid, bool $accepted): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->insert(self::TABLE, [
            'tstamp'       => time(),
            'category_uid' => $categoryUid,
            'accepted'     => $accepted ? 1 : 0,
        ]);
    }

    /**
     * Returns acceptance and rejection counts per category UID.
     *
     * @return array<int, array{accepted: int, rejected: int}>
     */
    public function getCountsPerCategory(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->select('category_uid', 'accepted')
            ->addSelectLiteral('COUNT(*) AS cnt')
            ->from(self::TABLE)
            ->groupBy('category_uid', 'accepted')
            ->executeQuery()
            ->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $rawUid = $row['category_uid'] ?? null;
            $uid = is_int($rawUid) ? $rawUid : (int)(is_string($rawUid) ? $rawUid : 0);

            if (!isset($result[$uid])) {
                $result[$uid] = ['accepted' => 0, 'rejected' => 0];
            }

            $rawAccepted = $row['accepted'] ?? null;
            $rawCnt = $row['cnt'] ?? null;
            $cnt = is_int($rawCnt) ? $rawCnt : (int)(is_string($rawCnt) ? $rawCnt : 0);

            if ((is_int($rawAccepted) ? $rawAccepted : (int)(is_string($rawAccepted) ? $rawAccepted : 0)) === 1) {
                $result[$uid]['accepted'] += $cnt;
            } else {
                $result[$uid]['rejected'] += $cnt;
            }
        }

        return $result;
    }

    /**
     * Returns per-day totals for the last N days.
     *
     * @return array<string, array{accepted: int, rejected: int}>
     */
    public function getDailyActivity(int $days = 30): array
    {
        $since = time() - ($days * 86400);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->select('accepted')
            ->addSelectLiteral('FROM_UNIXTIME(tstamp, \'%Y-%m-%d\') AS activity_date')
            ->addSelectLiteral('COUNT(*) AS cnt')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->gte('tstamp', $queryBuilder->createNamedParameter($since, ParameterType::INTEGER))
            )
            ->groupBy('activity_date', 'accepted')
            ->orderBy('activity_date', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $rawDate = $row['activity_date'] ?? null;
            $date = is_string($rawDate) ? $rawDate : '';

            if (!isset($result[$date])) {
                $result[$date] = ['accepted' => 0, 'rejected' => 0];
            }

            $rawAccepted = $row['accepted'] ?? null;
            $rawCnt = $row['cnt'] ?? null;
            $cnt = is_int($rawCnt) ? $rawCnt : (int)(is_string($rawCnt) ? $rawCnt : 0);

            if ((is_int($rawAccepted) ? $rawAccepted : (int)(is_string($rawAccepted) ? $rawAccepted : 0)) === 1) {
                $result[$date]['accepted'] += $cnt;
            } else {
                $result[$date]['rejected'] += $cnt;
            }
        }

        return $result;
    }

    public function deleteOldEntries(int $retentionDays): void
    {
        if ($retentionDays <= 0) {
            return;
        }

        $cutoff = time() - ($retentionDays * 86400);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->delete(self::TABLE)
            ->where(
                $queryBuilder->expr()->lt('tstamp', $queryBuilder->createNamedParameter($cutoff, ParameterType::INTEGER))
            )
            ->executeStatement();
    }
}
