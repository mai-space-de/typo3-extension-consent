<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

$newColumns = [
    'tx_maispace_consent_categories' => [
        'exclude' => true,
        'label'   => 'LLL:EXT:maispace_consent/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_maispace_consent_categories',
        'config'  => [
            'type'                => 'select',
            'renderType'          => 'selectMultipleSideBySide',
            'foreign_table'       => 'tx_maispace_consent_category',
            'foreign_table_where' => 'AND {#tx_maispace_consent_category}.{#deleted} = 0 AND {#tx_maispace_consent_category}.{#hidden} = 0 ORDER BY {#tx_maispace_consent_category}.{#sorting} ASC',
            'size'                => 5,
            'maxitems'            => 99,
            'minitems'            => 0,
            'fieldDescription'    => 'LLL:EXT:maispace_consent/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_maispace_consent_categories.description',
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('tt_content', $newColumns);

ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    '--div--;LLL:EXT:maispace_consent/Resources/Private/Language/locallang_tca.xlf:tab.consent,tx_maispace_consent_categories',
    '',
    'after:categories'
);
