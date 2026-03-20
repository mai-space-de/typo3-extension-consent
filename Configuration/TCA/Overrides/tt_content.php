<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

$newColumns = [
    'tx_maiconsent_categories' => [
        'exclude' => true,
        'label'   => 'LLL:EXT:mai_consent/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_maiconsent_categories',
        'config'  => [
            'type'                => 'select',
            'renderType'          => 'selectMultipleSideBySide',
            'foreign_table'       => 'tx_maiconsent_category',
            'foreign_table_where' => 'AND {#tx_maiconsent_category}.{#deleted} = 0 AND {#tx_maiconsent_category}.{#hidden} = 0 ORDER BY {#tx_maiconsent_category}.{#sorting} ASC',
            'size'                => 5,
            'maxitems'            => 99,
            'minitems'            => 0,
            'fieldDescription'    => 'LLL:EXT:mai_consent/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_maiconsent_categories.description',
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('tt_content', $newColumns);

ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    '--div--;LLL:EXT:mai_consent/Resources/Private/Language/locallang_tca.xlf:tab.consent,tx_maiconsent_categories',
    '',
    'after:categories'
);
