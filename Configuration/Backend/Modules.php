<?php

declare(strict_types=1);

use Maispace\MaiConsent\Controller\Backend\ConsentStatisticsBackendController;

return [
    'maispace_mai_consent' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user',
        'workspaceSupport' => false,
        'labels' => 'LLL:EXT:mai_consent/Resources/Private/Language/locallang.xlf',
        'extensionName' => 'MaiConsent',
        'iconIdentifier' => 'ext-maispace-mai_consent',
        'controllerActions' => [
            ConsentStatisticsBackendController::class => ['index'],
        ],
    ],
];
