<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title'          => 'LLL:EXT:maispace_consent/Resources/Private/Language/locallang_tca.xlf:tx_maispace_consent_category',
        'label'          => 'name',
        'sortby'         => 'sorting',
        'tstamp'         => 'tstamp',
        'crdate'         => 'crdate',
        'delete'         => 'deleted',
        'enablecolumns'  => [
            'disabled' => 'hidden',
        ],
        'searchFields'   => 'name,description',
        'iconfile'       => 'EXT:maispace_consent/ext_icon.svg',
    ],
    'types' => [
        '0' => [
            'showitem' => 'name, description, is_essential',
        ],
    ],
    'palettes' => [],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label'   => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config'  => [
                'type'       => 'check',
                'renderType' => 'checkboxToggle',
                'items'      => [
                    [
                        'label'              => '',
                        'invertStateDisplay' => true,
                    ],
                ],
            ],
        ],
        'name' => [
            'exclude' => false,
            'label'   => 'LLL:EXT:maispace_consent/Resources/Private/Language/locallang_tca.xlf:tx_maispace_consent_category.name',
            'config'  => [
                'type'     => 'input',
                'size'     => 50,
                'max'      => 255,
                'eval'     => 'trim',
                'required' => true,
            ],
        ],
        'description' => [
            'exclude' => false,
            'label'   => 'LLL:EXT:maispace_consent/Resources/Private/Language/locallang_tca.xlf:tx_maispace_consent_category.description',
            'config'  => [
                'type'            => 'text',
                'cols'            => 40,
                'rows'            => 5,
                'eval'            => 'trim',
                'enableRichtext'  => false,
            ],
        ],
        'is_essential' => [
            'exclude' => false,
            'label'   => 'LLL:EXT:maispace_consent/Resources/Private/Language/locallang_tca.xlf:tx_maispace_consent_category.is_essential',
            'config'  => [
                'type'       => 'check',
                'renderType' => 'checkboxToggle',
                'items'      => [
                    [
                        'label' => '',
                    ],
                ],
            ],
        ],
    ],
];
