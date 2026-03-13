<?php

declare(strict_types = 1);

namespace Maispace\MaispaceConsent\Middleware;

use Maispace\MaispaceConsent\Domain\Repository\StatisticRepository;
use Maispace\MaispaceConsent\Service\ConsentSettingsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;

class ConsentRecordMiddleware implements MiddlewareInterface
{
    private const RECORD_PATH = '/maispace/consent/record';

    public function __construct(
        private readonly StatisticRepository $statisticRepository,
        private readonly ConsentSettingsService $consentSettingsService,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $path = rtrim($uri->getPath(), '/');

        if ($request->getMethod() !== 'POST' || $path !== self::RECORD_PATH) {
            return $handler->handle($request);
        }

        $settings = $this->consentSettingsService->getSettings($request);
        $statisticsSettings = is_array($settings['statistics'] ?? null) ? $settings['statistics'] : [];

        // Honour statistics.enable = 0 TypoScript setting.
        if ((int)($statisticsSettings['enable'] ?? 1) === 0) {
            return new JsonResponse(['status' => 'ok']);
        }

        $body = (string)$request->getBody();
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['preferences']) || !is_array($data['preferences'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid payload'], 400);
        }

        $preferences = $data['preferences'];

        foreach ($preferences as $categoryUid => $accepted) {
            if (!is_bool($accepted)) {
                continue;
            }

            $this->statisticRepository->record((int)$categoryUid, $accepted);
        }

        // Purge old entries according to statistics.retentionDays.
        $retentionDays = (int)($statisticsSettings['retentionDays'] ?? 90);
        if ($retentionDays > 0) {
            $this->statisticRepository->deleteOldEntries($retentionDays);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
