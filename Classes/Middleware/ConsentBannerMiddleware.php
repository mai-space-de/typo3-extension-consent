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
                'cookie' => [
                    'name'     => 'maispace_consent',
                    'lifetime' => 365,
                    'sameSite' => 'Lax',
                ],
                'banner' => ['position' => 'bottom'],
                'modal'  => ['showCategoryDescriptions' => 1],
                'record' => ['endpoint' => '/maispace/consent/record'],
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

        // Extract runtime-configurable values from settings (may be overridden by event listeners).
        $settings = is_array($variables['settings'] ?? null) ? $variables['settings'] : [];
        $cookieSettings = is_array($settings['cookie'] ?? null) ? $settings['cookie'] : [];
        $recordSettings = is_array($settings['record'] ?? null) ? $settings['record'] : [];

        $cookieName = (is_string($cookieSettings['name'] ?? null) && $cookieSettings['name'] !== '')
            ? $cookieSettings['name'] : 'maispace_consent';
        $cookieLifetime = (is_int($cookieSettings['lifetime'] ?? null) && $cookieSettings['lifetime'] > 0)
            ? $cookieSettings['lifetime'] : 365;
        $cookieSameSite = (is_string($cookieSettings['sameSite'] ?? null) && $cookieSettings['sameSite'] !== '')
            ? $cookieSettings['sameSite'] : 'Lax';
        $recordEndpoint = (is_string($recordSettings['endpoint'] ?? null) && $recordSettings['endpoint'] !== '')
            ? $recordSettings['endpoint'] : '/maispace/consent/record';

        // JSON_HEX_TAG converts < and > to Unicode escapes, preventing </script> injection.
        $jsonFlags = JSON_THROW_ON_ERROR | JSON_HEX_TAG;

        $categoriesJson = json_encode($categoriesData, $jsonFlags);
        $configJson = json_encode([
            'cookieName'      => $cookieName,
            'cookieLifetime'  => $cookieLifetime,
            'cookieSameSite'  => $cookieSameSite,
            'recordEndpoint'  => $recordEndpoint,
        ], $jsonFlags);

        $injection = "\n"
            . '<script type="application/json" id="maispace-consent-config">'
            . $configJson
            . '</script>' . "\n"
            . '<script type="application/json" id="maispace-consent-categories">'
            . $categoriesJson
            . '</script>' . "\n"
            . $bannerHtml . "\n"
            . $modalHtml . "\n";

        $afterEvent = new AfterBannerRenderedEvent($injection);
        /** @var AfterBannerRenderedEvent $afterEvent */
        $afterEvent = $this->eventDispatcher->dispatch($afterEvent);
        $injection = $afterEvent->getHtml();

        $modifiedBody = str_replace('</body>', $injection . '</body>', $body);

        $stream = new Stream('php://temp', 'rw');
        $stream->write($modifiedBody);
        $stream->rewind();

        return $response->withBody($stream);
    }
}
