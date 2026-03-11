<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Middleware;

use Maispace\MaispaceConsent\Domain\Model\Category;
use Maispace\MaispaceConsent\Event\AfterBannerRenderedEvent;
use Maispace\MaispaceConsent\Event\BeforeBannerRenderedEvent;
use Maispace\MaispaceConsent\Service\BannerRenderer;
use Maispace\MaispaceConsent\Service\CategoryService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Stream;

class ConsentBannerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly BannerRenderer $bannerRenderer,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        $body = (string)$response->getBody();

        if (!str_contains($body, '</body>')) {
            return $response;
        }

        $categories = $this->categoryService->getAllCategories();

        $categoriesData = array_map(static fn (Category $c) => [
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

        $bannerHtml = $this->bannerRenderer->renderBannerHtml($variables);
        $modalHtml = $this->bannerRenderer->renderModalHtml($variables);
        $jsPath = $this->bannerRenderer->getJsPath();
        $cssPath = $this->bannerRenderer->getCssPath();

        $categoriesJson = json_encode($categoriesData, JSON_THROW_ON_ERROR);

        $cssLink = '<link rel="stylesheet" href="' . htmlspecialchars($cssPath, ENT_QUOTES | ENT_HTML5) . '">';
        $headInjection = "\n" . $cssLink . "\n";

        $injection = "\n"
            . '<script type="application/json" id="maispace-consent-categories">'
            . $categoriesJson
            . '</script>' . "\n"
            . $bannerHtml . "\n"
            . $modalHtml . "\n"
            . '<script id="maispace-consent-script" src="' . htmlspecialchars($jsPath, ENT_QUOTES | ENT_HTML5) . '"'
            . ' data-cookie-name="maispace_consent"'
            . ' data-cookie-lifetime="365"'
            . ' data-record-endpoint="/maispace/consent/record"'
            . ' defer></script>' . "\n";

        $afterEvent = new AfterBannerRenderedEvent($injection);
        /** @var AfterBannerRenderedEvent $afterEvent */
        $afterEvent = $this->eventDispatcher->dispatch($afterEvent);
        $injection = $afterEvent->getHtml();

        $modifiedBody = str_replace('</head>', $headInjection . '</head>', $body);
        $modifiedBody = str_replace('</body>', $injection . '</body>', $modifiedBody);

        $stream = new Stream('php://temp', 'rw');
        $stream->write($modifiedBody);
        $stream->rewind();

        return $response->withBody($stream);
    }
}
