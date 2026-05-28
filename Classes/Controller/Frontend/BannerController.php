<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Controller\Frontend;

use Maispace\MaiBase\Controller\AbstractActionController;
use Maispace\MaiConsent\Domain\Model\ConsentCategory;
use Maispace\MaiConsent\Domain\Repository\ConsentCategoryRepository;
use Psr\Http\Message\ResponseInterface;

class BannerController extends AbstractActionController
{
    public function __construct(
        private readonly ConsentCategoryRepository $categoryRepository,
    ) {}

    public function indexAction(): ResponseInterface
    {
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
}
