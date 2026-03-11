<?php

$EM_CONF[$_EXTKEY] = [
    'title'            => 'Maispace Consent',
    'description'      => 'Cookie consent management for TYPO3. Cookie banner, consent modal, category-based content gating and backend statistics.',
    'category'         => 'fe',
    'version'          => '1.0.0',
    'state'            => 'stable',
    'author'           => 'Maispace',
    'author_email'     => '',
    'author_company'   => 'Maispace',
    'clearCacheOnLoad' => true,
    'constraints'      => [
        'depends'   => [
            'php'   => '8.2.0-0.0.0',
            'typo3' => '13.4.0-13.9.99',
        ],
        'conflicts' => [],
        'suggests'  => [],
    ],
];
