<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Domain\Repository;

use Doctrine\DBAL\ParameterType;
use Maispace\MaispaceConsent\Domain\Model\Category;
use TYPO3\CMS\Core\Database\ConnectionPool;

class CategoryRepository
{
    private const TABLE = 'tx_maispace_consent_category';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    /**
     * Returns all non-deleted, non-hidden categories ordered by sorting.
     *
     * @return Category[]
     */
    public function findAll(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER))
            )
            ->orderBy('sorting', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn (array $row) => Category::fromRow($row), $rows);
    }

    public function findByUid(int $uid): ?Category
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return Category::fromRow($row);
    }

    public function insert(Category $category): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->insert(self::TABLE, [
            'pid'          => $category->getPid(),
            'tstamp'       => time(),
            'crdate'       => time(),
            'name'         => $category->getName(),
            'description'  => $category->getDescription(),
            'is_essential' => $category->isEssential() ? 1 : 0,
            'sorting'      => $category->getSorting(),
        ]);

        $uid = intval($connection->lastInsertId());
        $category->setUid($uid);
    }

    public function update(Category $category): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->update(
            self::TABLE,
            [
                'tstamp'       => time(),
                'name'         => $category->getName(),
                'description'  => $category->getDescription(),
                'is_essential' => $category->isEssential() ? 1 : 0,
                'sorting'      => $category->getSorting(),
            ],
            ['uid' => $category->getUid()]
        );
    }

    public function delete(int $uid): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->update(
            self::TABLE,
            [
                'deleted' => 1,
                'tstamp'  => time(),
            ],
            ['uid' => $uid]
        );
    }

    /**
     * Updates sorting for multiple categories at once.
     *
     * @param int[] $sortedUids UIDs in desired sort order (index = sorting value)
     */
    public function updateSorting(array $sortedUids): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        foreach ($sortedUids as $sorting => $uid) {
            $connection->update(
                self::TABLE,
                ['sorting' => $sorting],
                ['uid'     => (int)$uid]
            );
        }
    }
}
