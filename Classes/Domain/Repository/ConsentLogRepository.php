<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Domain\Repository;

use Maispace\MaiConsent\Domain\Model\ConsentLog;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<ConsentLog>
 */
class ConsentLogRepository extends Repository
{
    /**
     * @return array<int, array{total: int, accepted: int}>
     */
    public function countPerCategory(): array
    {
        $connection = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Database\ConnectionPool::class,
        )->getConnectionForTable('tx_maiconsent_domain_model_consentlog');

        $rows = $connection->executeQuery(
            'SELECT category, accepted, COUNT(*) AS cnt
             FROM tx_maiconsent_domain_model_consentlog
             WHERE deleted = 0
             GROUP BY category, accepted',
        )->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $catUid = (int) $row['category'];
            if (!isset($result[$catUid])) {
                $result[$catUid] = ['total' => 0, 'accepted' => 0];
            }
            $result[$catUid]['total'] += (int) $row['cnt'];
            if ((int) $row['accepted'] === 1) {
                $result[$catUid]['accepted'] += (int) $row['cnt'];
            }
        }

        return $result;
    }

    public function addEntry(int $categoryUid, bool $accepted, string $session, string $ipAddress, int $storagePid): void
    {
        $connection = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Database\ConnectionPool::class,
        )->getConnectionForTable('tx_maiconsent_domain_model_consentlog');

        $connection->insert('tx_maiconsent_domain_model_consentlog', [
            'pid'        => $storagePid,
            'crdate'     => time(),
            'tstamp'     => time(),
            'category'   => $categoryUid,
            'accepted'   => $accepted ? 1 : 0,
            'session'    => $session,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Physically removes consent log entries created before the given cutoff
     * timestamp. The stored IP address is personal data, so expired entries
     * are hard-deleted (erased) rather than soft-deleted to satisfy GDPR
     * data-minimisation / retention requirements.
     *
     * @return int Number of removed rows.
     */
    public function deleteOlderThan(int $cutoffTimestamp): int
    {
        $queryBuilder = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Database\ConnectionPool::class,
        )->getQueryBuilderForTable('tx_maiconsent_domain_model_consentlog');

        return (int) $queryBuilder
            ->delete('tx_maiconsent_domain_model_consentlog')
            ->where(
                $queryBuilder->expr()->lt(
                    'crdate',
                    $queryBuilder->createNamedParameter($cutoffTimestamp, \Doctrine\DBAL\ParameterType::INTEGER),
                ),
            )
            ->executeStatement();
    }
}
