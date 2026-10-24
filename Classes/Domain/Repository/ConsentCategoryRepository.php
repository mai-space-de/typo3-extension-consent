<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Domain\Repository;

use Maispace\MaiConsent\Domain\Model\ConsentCategory;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<ConsentCategory>
 */
class ConsentCategoryRepository extends Repository
{
    public function findAllOrdered(): array
    {
        $query = $this->createQuery();
        // Backend modules fall back to the selected page as storagePid when
        // plugin TypoScript is missing; categories live on a fixed storage
        // folder and identifiers are globally unique — never hide them.
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->setOrderings(['sorting' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING]);
        return $query->execute()->toArray();
    }

    public function findOneByIdentifier(string $identifier): ?ConsentCategory
    {
        $query = $this->createQuery();
        // API middleware has no Extbase persistence.storagePid; identifiers are
        // globally unique so storage-page filtering must not hide categories.
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('identifier', $identifier));
        return $query->execute()->getFirst();
    }
}
