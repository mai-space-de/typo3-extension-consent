<?php

declare(strict_types=1);

return [
    'maispace_consent' => [
        'parent'         => 'web',
        'position'       => ['after' => 'web_info'],
        'access'         => 'user',
        'workspaces'     => 'live',
        'iconIdentifier' => 'maispace-consent',
        'path'           => '/module/maispace/consent',
        'labels'         => 'LLL:EXT:maispace_consent/Resources/Private/Language/locallang_mod.xlf',
        'routes'         => [
            '_default' => [
                'target' => \Maispace\MaispaceConsent\Controller\Backend\ConsentController::class . '::indexAction',
            ],
            'statistics' => [
                'target' => \Maispace\MaispaceConsent\Controller\Backend\ConsentController::class . '::statisticsAction',
            ],
            'createCategory' => [
                'target' => \Maispace\MaispaceConsent\Controller\Backend\ConsentController::class . '::createCategoryAction',
            ],
            'updateCategory' => [
                'target' => \Maispace\MaispaceConsent\Controller\Backend\ConsentController::class . '::updateCategoryAction',
            ],
            'deleteCategory' => [
                'target' => \Maispace\MaispaceConsent\Controller\Backend\ConsentController::class . '::deleteCategoryAction',
            ],
            'exportStatisticsCsv' => [
                'target' => \Maispace\MaispaceConsent\Controller\Backend\ConsentController::class . '::exportStatisticsCsvAction',
            ],
        ],
    ],
];
