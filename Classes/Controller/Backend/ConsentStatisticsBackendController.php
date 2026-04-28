<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Controller\Backend;

use Maispace\MaiBase\Controller\Backend\AbstractBackendController;
use Maispace\MaiConsent\Domain\Repository\ConsentCategoryRepository;
use Maispace\MaiConsent\Domain\Repository\ConsentLogRepository;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;

#[AsController]
class ConsentStatisticsBackendController extends AbstractBackendController
{
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        IconFactory $iconFactory,
        private readonly ConsentCategoryRepository $categoryRepository,
        private readonly ConsentLogRepository $logRepository,
    ) {
        parent::__construct($moduleTemplateFactory, $iconFactory);
    }

    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->createModuleTemplate();
        $this->addShortcutButton($moduleTemplate, 'maispace_mai_consent', 'Consent Statistics');

        $categories = $this->categoryRepository->findAllOrdered();
        $statsPerCategory = $this->logRepository->countPerCategory();

        $stats = [];
        foreach ($categories as $category) {
            $catUid = $category->getUid();
            $counts = $statsPerCategory[$catUid] ?? ['total' => 0, 'accepted' => 0];
            $stats[] = [
                'category' => $category,
                'total'    => $counts['total'],
                'accepted' => $counts['accepted'],
                'rate'     => $counts['total'] > 0 ? round($counts['accepted'] / $counts['total'] * 100, 1) : 0,
            ];
        }

        $this->assignMultiple($moduleTemplate, [
            'stats' => $stats,
        ]);

        return $this->renderModuleResponse($moduleTemplate, 'Index');
    }
}
