<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Controller\Frontend;

use Maispace\MaiBase\Controller\AbstractActionController;
use Maispace\MaiConsent\Domain\Model\ConsentCategory;
use Maispace\MaiConsent\Domain\Repository\ConsentCategoryRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheTag;

class BannerController extends AbstractActionController
{
    /**
     * Page cache tag of every cached page that contains the banner.
     * Flushed by ConsentCategoryCacheInvalidationHook.
     */
    public const string CACHE_TAG = 'tx_maiconsent_banner';

    public function __construct(
        private readonly ConsentCategoryRepository $categoryRepository,
    ) {}

    public function indexAction(): ResponseInterface
    {
        $this->tagPageCache();

        $categories = $this->categoryRepository->findAllOrdered();
        $this->view->assign('categories', $categories);
        $this->view->assign('categoryVersion', self::computeCategoryVersion($categories));
        return $this->htmlResponse();
    }

    /**
     * Compute a short version hash from the sorted list of active category identifiers.
     * When categories are added, removed, or renamed the hash changes, causing the JS
     * to detect a version mismatch and show the consent banner again.
     *
     * @param ConsentCategory[] $categories
     */
    public static function computeCategoryVersion(array $categories): string
    {
        $identifiers = array_map(
            static fn(ConsentCategory $c): string => $c->getIdentifier(),
            $categories,
        );
        sort($identifiers);
        return substr(md5(implode(',', $identifiers)), 0, 8);
    }

    /**
     * The banner is cacheable page content. Extbase only registers cache tags for
     * records it hydrates through its own persistence layer, which does not cover
     * a changed category reliably, so the banner carries an explicit tag.
     */
    private function tagPageCache(): void
    {
        $collector = $this->request->getAttribute('frontend.cache.collector');
        if ($collector instanceof CacheDataCollector) {
            $collector->addCacheTags(new CacheTag(self::CACHE_TAG));
        }
    }
}
