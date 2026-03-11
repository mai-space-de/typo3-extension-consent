<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Controller\Backend;

use Maispace\MaispaceConsent\Domain\Repository\StatisticRepository;
use Maispace\MaispaceConsent\Service\CategoryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class ConsentController
{
    private const EXT_KEY = 'maispace_consent';

    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly StatisticRepository $statisticRepository,
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly UriBuilder $uriBuilder,
    ) {
    }

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $categories = $this->categoryService->getAllCategories();

        $view = $this->createView('Backend/Index');
        $view->assignMultiple([
            'categories'    => $categories,
            'statisticsUri' => (string)$this->uriBuilder->buildUriFromRoute('maispace_consent.statistics'),
        ]);

        return $moduleTemplate->renderResponse('Backend/Index');
    }

    public function statisticsAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $countsPerCategory = $this->statisticRepository->getCountsPerCategory();
        $categories = $this->categoryService->getAllCategories();

        $statisticsData = [];
        foreach ($categories as $category) {
            $uid = $category->getUid();
            $counts = $countsPerCategory[$uid] ?? ['accepted' => 0, 'rejected' => 0];
            $total = $counts['accepted'] + $counts['rejected'];
            $acceptRate = $total > 0 ? round(($counts['accepted'] / $total) * 100, 1) : 0;

            $statisticsData[] = [
                'category'   => $category,
                'accepted'   => $counts['accepted'],
                'rejected'   => $counts['rejected'],
                'total'      => $total,
                'acceptRate' => $acceptRate,
            ];
        }

        $dailyActivity = $this->statisticRepository->getDailyActivity(30);

        $view = $this->createView('Backend/Statistics');
        $view->assignMultiple([
            'statisticsData' => $statisticsData,
            'dailyActivity'  => $dailyActivity,
            'indexUri'       => (string)$this->uriBuilder->buildUriFromRoute('maispace_consent'),
        ]);

        return $moduleTemplate->renderResponse('Backend/Statistics');
    }

    public function createCategoryAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody)) {
            $name = trim(is_string($parsedBody['name'] ?? null) ? $parsedBody['name'] : '');
            $description = trim(is_string($parsedBody['description'] ?? null) ? $parsedBody['description'] : '');
            $isEssential = isset($parsedBody['is_essential']) && (bool)$parsedBody['is_essential'];

            if ($name !== '') {
                $this->categoryService->createCategory($name, $description, $isEssential);
            }
        }

        return new RedirectResponse(
            (string)$this->uriBuilder->buildUriFromRoute('maispace_consent'),
            303
        );
    }

    public function updateCategoryAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody)) {
            $uid = is_numeric($parsedBody['uid'] ?? null) ? (int)$parsedBody['uid'] : 0;
            $name = trim(is_string($parsedBody['name'] ?? null) ? $parsedBody['name'] : '');
            $description = trim(is_string($parsedBody['description'] ?? null) ? $parsedBody['description'] : '');
            $isEssential = isset($parsedBody['is_essential']) && (bool)$parsedBody['is_essential'];

            if ($uid > 0 && $name !== '') {
                $this->categoryService->updateCategory($uid, $name, $description, $isEssential);
            }
        }

        return new RedirectResponse(
            (string)$this->uriBuilder->buildUriFromRoute('maispace_consent'),
            303
        );
    }

    public function deleteCategoryAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody)) {
            $uid = is_numeric($parsedBody['uid'] ?? null) ? (int)$parsedBody['uid'] : 0;

            if ($uid > 0) {
                $this->categoryService->deleteCategory($uid);
            }
        }

        return new RedirectResponse(
            (string)$this->uriBuilder->buildUriFromRoute('maispace_consent'),
            303
        );
    }

    private function createView(string $template): StandaloneView
    {
        $extPath = ExtensionManagementUtility::extPath(self::EXT_KEY);

        $view = new StandaloneView();
        $view->setTemplateRootPaths([$extPath . 'Resources/Private/Templates/']);
        $view->setPartialRootPaths([$extPath . 'Resources/Private/Partials/']);
        $view->setLayoutRootPaths([$extPath . 'Resources/Private/Layouts/']);
        $view->setTemplate($template);

        return $view;
    }
}
