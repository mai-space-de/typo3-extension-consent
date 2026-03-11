<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;

class StatisticRepository
{
    private const TABLE = 'tx_maispace_consent_statistic';

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
            $uid = (int)$row['category_uid'];
            if (!isset($result[$uid])) {
                $result[$uid] = ['accepted' => 0, 'rejected' => 0];
            }

            if ((int)$row['accepted'] === 1) {
                $result[$uid]['accepted'] += (int)$row['cnt'];
            } else {
                $result[$uid]['rejected'] += (int)$row['cnt'];
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
                $queryBuilder->expr()->gte('tstamp', $queryBuilder->createNamedParameter($since, \PDO::PARAM_INT))
            )
            ->groupBy('activity_date', 'accepted')
            ->orderBy('activity_date', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $date = (string)$row['activity_date'];
            if (!isset($result[$date])) {
                $result[$date] = ['accepted' => 0, 'rejected' => 0];
            }

            if ((int)$row['accepted'] === 1) {
                $result[$date]['accepted'] += (int)$row['cnt'];
            } else {
                $result[$date]['rejected'] += (int)$row['cnt'];
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
                $queryBuilder->expr()->lt('tstamp', $queryBuilder->createNamedParameter($cutoff, \PDO::PARAM_INT))
            )
            ->executeStatement();
    }
}
