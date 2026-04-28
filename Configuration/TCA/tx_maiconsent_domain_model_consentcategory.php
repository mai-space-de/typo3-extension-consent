<?php

declare(strict_types=1);

use Maispace\MaiBase\TableConfigurationArray\FieldConfig\CheckboxConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\InputConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\SlugConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\TextConfig;
use Maispace\MaiBase\TableConfigurationArray\Helper;
use Maispace\MaiBase\TableConfigurationArray\Table;

$lang = Helper::localLangHelperFactory('mai_consent', 'Default/locallang_tca.xlf');

return (new Table($lang('table.tx_maiconsent_category')))
    ->setDefaultConfig()
    ->setLabel('title')
    ->setIconFile('EXT:mai_consent/Resources/Public/Icons/tx_maiconsent_category.svg')
    ->setSortingField()
    ->addColumn(
        'title',
        $lang('tx_maiconsent_category.title'),
        (new InputConfig())->setSize(50)->setMax(255)->setEval('trim')->setRequired()
    )
    ->addColumn(
        'identifier',
        $lang('tx_maiconsent_category.identifier'),
        (new SlugConfig())
            ->setGeneratorOptions([
                'fields' => ['title'],
                'replacements' => [' ' => '_'],
            ])
            ->setFallbackCharacter('_')
            ->setEval('uniqueInSite')
    )
    ->addColumn(
        'description',
        $lang('tx_maiconsent_category.description'),
        (new TextConfig())->setRows(5)->setCols(50)->setEval('trim')
    )
    ->addColumn(
        'is_required',
        $lang('tx_maiconsent_category.is_required'),
        (new CheckboxConfig())->setRenderType('checkboxToggle')->setDefault(0)
    )
    ->addTypeShowItem(
        '0',
        'title, identifier, description, is_required,
        --div--;' . $lang('tab.language') . ', --palette--;;language,
        --div--;' . $lang('tab.access') . ', --palette--;;hidden, --palette--;;access'
    )
    ->getConfig();
