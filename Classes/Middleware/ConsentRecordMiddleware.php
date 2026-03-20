<?php

declare(strict_types = 1);

namespace Maispace\MaiConsent\Middleware;

use Maispace\MaiConsent\Domain\Repository\StatisticRepository;
use Maispace\MaiConsent\Service\ConsentSettingsService;
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
        $enableRaw = $statisticsSettings['enable'] ?? 1;
        if (is_numeric($enableRaw) && (int)$enableRaw === 0) {
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
        $retentionDaysRaw = $statisticsSettings['retentionDays'] ?? 90;
        $retentionDays = is_numeric($retentionDaysRaw) ? (int)$retentionDaysRaw : 90;
        if ($retentionDays > 0) {
            $this->statisticRepository->deleteOldEntries($retentionDays);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
