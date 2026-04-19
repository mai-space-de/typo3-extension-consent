<?php

declare(strict_types=1);

use Maispace\MaiConsent\Middleware\ConsentApiMiddleware;

return [
    'frontend' => [
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
