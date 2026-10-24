<?php

declare(strict_types=1);

$allIcons = 'EXT:mai_base/Resources/Public/Icons/all-icons/';

return [
    'ext-maispace-mai_consent' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        'source' => 'EXT:mai_consent/Resources/Public/Icons/Extension.svg',
    ],
    'mai-consent-module' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        'source' => $allIcons . 'interface-security-shield-1--shield-protection-security-defend-crime-war-cover--Streamline-Core.svg',
    ],
    'mai-consent-category' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        'source' => $allIcons . 'interface-lock-shield--combination-combo-lock-locked-padlock-secure-security-shield-keyhole--Streamline-Core.svg',
    ],
    'mai-consent-log' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        'source' => $allIcons . 'interface-file-clipboard-check--checkmark-edit-task-edition-checklist-check-success-clipboard-form--Streamline-Core.svg',
    ],
];
