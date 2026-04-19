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
        $query->setOrderings(['sorting' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING]);
        return $query->execute()->toArray();
    }

    public function findByIdentifier(string $identifier): ?ConsentCategory
    {
        $query = $this->createQuery();
        $query->matching($query->equals('identifier', $identifier));
        return $query->execute()->getFirst();
    }
}
