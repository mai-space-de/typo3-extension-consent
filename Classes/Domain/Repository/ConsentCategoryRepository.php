<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Domain\Repository;

use Maispace\MaiConsent\Domain\Model\ConsentCategory;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<ConsentCategory>
 */
class ConsentCategoryRepository extends Repository
{
    public function findAllOrdered(): array
    {
        $query = $this->createQuery();
        $this->keepDefaultLanguageCategories($query);
        $query->setOrderings(['sorting' => QueryInterface::ORDER_ASCENDING]);
        return $query->execute()->toArray();
    }

    public function findOneByIdentifier(string $identifier): ?ConsentCategory
    {
        $query = $this->createQuery();
        $this->keepDefaultLanguageCategories($query);
        $query->matching($query->equals('identifier', $identifier));
        return $query->execute()->getFirst();
    }

    /**
     * Kategorien sind seitenweit und meist nur in der Standardsprache angelegt.
     * Sprachen mit fallbackType strict würden sie sonst ausblenden, der Banner
     * bliebe ohne Schalter. Eine vorhandene Übersetzung liegt weiterhin darüber.
     */
    private function keepDefaultLanguageCategories(QueryInterface $query): void
    {
        $settings = $query->getQuerySettings();
        // Backend und die API haben oft kein storagePid; Identifier sind eindeutig.
        $settings->setRespectStoragePage(false);
        $aspect = $settings->getLanguageAspect();
        $settings->setLanguageAspect(new LanguageAspect(
            $aspect->getId(),
            $aspect->getContentId(),
            LanguageAspect::OVERLAYS_MIXED,
            $aspect->getFallbackChain(),
        ));
    }
}
