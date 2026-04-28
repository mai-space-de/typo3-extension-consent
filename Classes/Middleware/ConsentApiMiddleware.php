<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Middleware;

use Maispace\MaiBase\Middleware\Api\AbstractApiMiddleware;
use Maispace\MaiConsent\Domain\Repository\ConsentCategoryRepository;
use Maispace\MaiConsent\Domain\Repository\ConsentLogRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;

class ConsentApiMiddleware extends AbstractApiMiddleware
{
    private const API_PATH = '/api/consent';

    public function __construct(
        private readonly ConsentCategoryRepository $categoryRepository,
        private readonly ConsentLogRepository $logRepository,
        private readonly Context $context,
    ) {}

    public function shouldHandle(ServerRequestInterface $request): bool
    {
        return str_starts_with($request->getUri()->getPath(), self::API_PATH);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isMethod($request, 'POST')) {
            return $this->errorResponse('Method not allowed', 405);
        }

        if (!$this->isJsonRequest($request)) {
            return $this->errorResponse('Content-Type must be application/json', 415);
        }

        $body = $this->decodeJsonBody($request);
        if ($body === null) {
            return $this->errorResponse('Invalid JSON body', 400);
        }

        $consents = $body['consents'] ?? [];
        if (!is_array($consents)) {
            return $this->errorResponse('Missing or invalid "consents" array', 422);
        }

        $storagePid = (int)($body['storagePid'] ?? 0);
        $session = $request->getAttribute('frontend.user')?->id ?? session_id() ?: '';
        $ipAddress = $this->anonymizeIp($request->getServerParams()['REMOTE_ADDR'] ?? '');

        foreach ($consents as $entry) {
            $identifier = (string)($entry['identifier'] ?? '');
            $accepted   = (bool)($entry['accepted'] ?? false);

            $category = $this->categoryRepository->findOneByIdentifier($identifier);
            if ($category === null) {
                continue;
            }

            $this->logRepository->addEntry(
                (int)$category->getUid(),
                $accepted,
                $session,
                $ipAddress,
                $storagePid
            );
        }

        return $this->jsonResponse(['success' => true]);
    }

    private function anonymizeIp(string $ip): string
    {
        if (str_contains($ip, ':')) {
            return preg_replace('/:[^:]*$/', ':0', $ip) ?? $ip;
        }
        return preg_replace('/\.\d+$/', '.0', $ip) ?? $ip;
    }
}
