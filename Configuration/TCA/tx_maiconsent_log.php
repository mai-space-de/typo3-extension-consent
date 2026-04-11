<?php

declare(strict_types=1);

use Maispace\MaiBase\TableConfigurationArray\Helper;
use Maispace\MaiBase\TableConfigurationArray\Table;

$lang = Helper::localLangHelperFactory('mai_consent', 'Default/locallang_tca.xlf');

return (new Table($lang('table.tx_maiconsent_log')))
    ->setDefaultConfig()
    ->setLabel('category')
    ->setAlternativeLabelFields('session')
    ->setSearchFields('session, ip_address')
    ->setIconFile('EXT:mai_consent/Resources/Public/Icons/tx_maiconsent_log.svg')
    ->setDefaultSorting('ORDER BY crdate DESC')
    ->recordsCanOnlyBeRead()
    ->addColumn(
        'category',
        $lang('tx_maiconsent_log.category'),
        [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'foreign_table' => 'tx_maiconsent_category',
            'foreign_table_where' => 'ORDER BY tx_maiconsent_category.title',
            'minitems' => 1,
            'maxitems' => 1,
        ]
    )
    ->addColumn(
        'accepted',
        $lang('tx_maiconsent_log.accepted'),
        [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'default' => 0,
            'readOnly' => true,
        ]
    )
    ->addColumn(
        'session',
        $lang('tx_maiconsent_log.session'),
        ['type' => 'input', 'size' => 50, 'max' => 255, 'readOnly' => true]
    )
    ->addColumn(
        'ip_address',
        $lang('tx_maiconsent_log.ip_address'),
        ['type' => 'input', 'size' => 50, 'max' => 45, 'readOnly' => true]
    )
    ->addTypeShowItem(
        '0',
        'category, accepted, session, ip_address'
    )
    ->getConfig();
