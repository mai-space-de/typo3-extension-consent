<?php

declare(strict_types=1);

use Maispace\MaiBase\TableConfigurationArray\FieldConfig\CheckboxConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\InputConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\SelectSingleConfig;
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
        (new SelectSingleConfig())
            ->setForeignTable('tx_maiconsent_category')
            ->setForeignTableWhere('ORDER BY tx_maiconsent_category.title')
            ->setMinItems(1)
            ->setMaxItems(1)
    )
    ->addColumn(
        'accepted',
        $lang('tx_maiconsent_log.accepted'),
        (new CheckboxConfig())->setRenderType('checkboxToggle')->setDefault(0)->setReadOnly()
    )
    ->addColumn(
        'session',
        $lang('tx_maiconsent_log.session'),
        (new InputConfig())->setSize(50)->setMax(255)->setReadOnly()
    )
    ->addColumn(
        'ip_address',
        $lang('tx_maiconsent_log.ip_address'),
        (new InputConfig())->setSize(50)->setMax(45)->setReadOnly()
    )
    ->addTypeShowItem(
        '0',
        'category, accepted, session, ip_address'
    )
    ->getConfig();
