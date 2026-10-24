<?php

declare(strict_types=1);

use Maispace\MaiConsent\Middleware\ConsentApiMiddleware;

return [
    'frontend' => [
        // 'after: site' alone can place this after page-resolver, which 404s
        // unknown paths (incl. /api/consent) before this middleware runs.
        // 'before: page-resolver' matches search/assets API middlewares.
        'maispace/mai-consent/consent-api' => [
            'target' => ConsentApiMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
