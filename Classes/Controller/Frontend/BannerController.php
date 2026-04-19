<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Controller\Frontend;

use Maispace\MaiBase\Controller\AbstractActionController;
use Maispace\MaiConsent\Domain\Repository\ConsentCategoryRepository;
use Psr\Http\Message\ResponseInterface;

class BannerController extends AbstractActionController
{
    public function __construct(
        private readonly ConsentCategoryRepository $categoryRepository,
    ) {}

    public function indexAction(): ResponseInterface
    {
        $this->view->assign('categories', $this->categoryRepository->findAllOrdered());
        return $this->htmlResponse();
    }
}
