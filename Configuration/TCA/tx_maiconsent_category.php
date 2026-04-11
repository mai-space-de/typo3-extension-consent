<?php

declare(strict_types=1);

use Maispace\MaiBase\TableConfigurationArray\Helper;
use Maispace\MaiBase\TableConfigurationArray\Table;

$lang = Helper::localLangHelperFactory('mai_consent', 'Default/locallang_tca.xlf');

return (new Table($lang('table.tx_maiconsent_category')))
    ->setDefaultConfig()
    ->setLabel('title')
    ->setSearchFields('title, identifier, description')
    ->setIconFile('EXT:mai_consent/Resources/Public/Icons/tx_maiconsent_category.svg')
    ->setSortingField()
    ->addColumn(
        'title',
        $lang('tx_maiconsent_category.title'),
        ['type' => 'input', 'size' => 50, 'max' => 255, 'eval' => 'trim,required']
    )
    ->addColumn(
        'identifier',
        $lang('tx_maiconsent_category.identifier'),
        [
            'type' => 'slug',
            'size' => 50,
            'generatorOptions' => [
                'fields' => ['title'],
                'replacements' => [' ' => '_'],
            ],
            'fallbackCharacter' => '_',
            'eval' => 'uniqueInSite',
        ]
    )
    ->addColumn(
        'description',
        $lang('tx_maiconsent_category.description'),
        ['type' => 'text', 'rows' => 5, 'cols' => 50, 'eval' => 'trim']
    )
    ->addColumn(
        'is_required',
        $lang('tx_maiconsent_category.is_required'),
        [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'default' => 0,
        ]
    )
    ->addTypeShowItem(
        '0',
        'title, identifier, description, is_required,
        --div--;' . $lang('tab.language') . ', --palette--;;language,
        --div--;' . $lang('tab.access') . ', --palette--;;hidden, --palette--;;access'
    )
    ->getConfig();
