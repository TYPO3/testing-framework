<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'typo3/testing-framework package test extension',
    'description' => '',
    'category' => 'be',
    'state' => 'stable',
    'author' => 'TYPO3 Core Team',
    'author_email' => 'typo3cms@typo3.org',
    'author_company' => '',
    'version' => '13.4.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0',
            'package1' => '0.0.0-9.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
