<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Middleware;

use Maispace\MaiBase\Middleware\Api\AbstractApiMiddleware;
use Maispace\MaiConsent\Domain\Repository\ConsentCategoryRepository;
use Maispace\MaiConsent\Domain\Repository\ConsentLogRepository;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

class ConsentApiMiddleware extends AbstractApiMiddleware
{
    private const API_PATH = '/api/consent';

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        private readonly ConsentCategoryRepository $categoryRepository,
        private readonly ConsentLogRepository $logRepository,
    ) {
        parent::__construct($responseFactory, $streamFactory);
    }

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
        $consents = $body['consents'] ?? [];
        if (!is_array($consents)) {
            return $this->errorResponse('Missing or invalid "consents" array', 422);
        }

        $storagePid = (int) ($body['storagePid'] ?? 0);
        $session = $this->resolveSessionId($request);
        $ipAddress = $this->resolveIpAddress($request);

        foreach ($consents as $entry) {
            $identifier = (string) ($entry['identifier'] ?? '');
            $accepted   = (bool) ($entry['accepted'] ?? false);

            $category = $this->categoryRepository->findOneByIdentifier($identifier);
            if ($category === null) {
                continue;
            }

            $this->logRepository->addEntry(
                (int) $category->getUid(),
                $accepted,
                $session,
                $ipAddress,
                $storagePid,
            );
        }

        return $this->jsonResponse(['success' => true]);
    }

    private function resolveSessionId(ServerRequestInterface $request): string
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if ($frontendUser instanceof FrontendUserAuthentication) {
            try {
                $sessionId = $frontendUser->getSession()->getIdentifier();
                if ($sessionId !== '') {
                    return $sessionId;
                }
            } catch (\Throwable) {
                // No session yet — fall through to PHP session id.
            }
        }

        return session_id() ?: '';
    }

    private function resolveIpAddress(ServerRequestInterface $request): string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '';

        if ($this->isIpAnonymisationEnabled()) {
            return $this->anonymizeIp($ip);
        }

        return $ip;
    }

    protected function isIpAnonymisationEnabled(): bool
    {
        try {
            $configurationManager = GeneralUtility::makeInstance(ConfigurationManagerInterface::class);
            $settings = $configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
                'MaiConsent',
            );

            return (bool) ($settings['ipAnonymisation'] ?? true);
        } catch (\Throwable) {
            return true;
        }
    }

    private function anonymizeIp(string $ip): string
    {
        if (str_contains($ip, ':')) {
            return preg_replace('/:[^:]*$/', ':0', $ip) ?? $ip;
        }
        return preg_replace('/\.\d+$/', '.0', $ip) ?? $ip;
    }
}
