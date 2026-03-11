<?php

declare(strict_types=1);

return [
    'frontend' => [
        'maispace/consent/banner' => [
            'target' => \Maispace\MaispaceConsent\Middleware\ConsentBannerMiddleware::class,
            'after'  => ['typo3/cms-frontend/content-length-headers'],
            'before' => ['typo3/cms-frontend/output-compression'],
        ],
        'maispace/consent/record' => [
            'target' => \Maispace\MaispaceConsent\Middleware\ConsentRecordMiddleware::class,
            'before' => ['maispace/consent/banner'],
        ],
    ],
];
