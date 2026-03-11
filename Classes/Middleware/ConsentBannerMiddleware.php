<?php

declare(strict_types=1);

namespace Maispace\MaispaceConsent\Middleware;

use Maispace\MaispaceConsent\Domain\Model\Category;
use Maispace\MaispaceConsent\Event\AfterBannerRenderedEvent;
use Maispace\MaispaceConsent\Event\BeforeBannerRenderedEvent;
use Maispace\MaispaceConsent\Service\CategoryService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class ConsentBannerMiddleware implements MiddlewareInterface
{
    private const EXT_KEY = 'maispace_consent';

    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        $categories = $this->categoryService->getAllCategories();

        $categoriesData = array_map(static fn(Category $c) => [
            'uid'         => $c->getUid(),
            'name'        => $c->getName(),
            'isEssential' => $c->isEssential(),
        ], $categories);

        $variables = [
            'categories' => $categories,
            'settings'   => [
                'banner' => ['position' => 'bottom'],
                'modal'  => ['showCategoryDescriptions' => 1],
            ],
        ];

        $beforeEvent = new BeforeBannerRenderedEvent($variables);
        /** @var BeforeBannerRenderedEvent $beforeEvent */
        $beforeEvent = $this->eventDispatcher->dispatch($beforeEvent);

        if (!$beforeEvent->isEnabled()) {
            return $response;
        }

        $variables = $beforeEvent->getVariables();

        $extPath = ExtensionManagementUtility::extPath(self::EXT_KEY);

        $bannerView = new StandaloneView();
        $bannerView->setTemplatePathAndFilename(
            $extPath . 'Resources/Private/Partials/Consent/Banner.html'
        );
        $bannerView->setPartialRootPaths([$extPath . 'Resources/Private/Partials/']);
        $bannerView->setLayoutRootPaths([$extPath . 'Resources/Private/Layouts/']);
        $bannerView->assignMultiple($variables);
        $bannerHtml = $bannerView->render();

        $modalView = new StandaloneView();
        $modalView->setTemplatePathAndFilename(
            $extPath . 'Resources/Private/Partials/Consent/Modal.html'
        );
        $modalView->setPartialRootPaths([$extPath . 'Resources/Private/Partials/']);
        $modalView->setLayoutRootPaths([$extPath . 'Resources/Private/Layouts/']);
        $modalView->assignMultiple($variables);
        $modalHtml = $modalView->render();

        $jsPath = PathUtility::getAbsoluteWebPath(
            $extPath . 'Resources/Public/JavaScript/consent.js'
        );

        $categoriesJson = json_encode($categoriesData, JSON_THROW_ON_ERROR);

        $injection = "\n"
            . '<script type="application/json" id="maispace-consent-categories">'
            . $categoriesJson
            . '</script>' . "\n"
            . $bannerHtml . "\n"
            . $modalHtml . "\n"
            . '<script type="module" src="' . htmlspecialchars($jsPath, ENT_QUOTES | ENT_HTML5) . '"'
            . ' data-cookie-name="maispace_consent"'
            . ' data-cookie-lifetime="365"'
            . ' data-record-endpoint="/maispace/consent/record"'
            . '></script>' . "\n";

        $afterEvent = new AfterBannerRenderedEvent($injection);
        /** @var AfterBannerRenderedEvent $afterEvent */
        $afterEvent = $this->eventDispatcher->dispatch($afterEvent);
        $injection = $afterEvent->getHtml();

        $body = (string)$response->getBody();

        if (!str_contains($body, '</body>')) {
            return $response;
        }

        $modifiedBody = str_replace('</body>', $injection . '</body>', $body);

        $stream = new Stream('php://temp', 'rw');
        $stream->write($modifiedBody);
        $stream->rewind();

        return $response->withBody($stream);
    }
}
