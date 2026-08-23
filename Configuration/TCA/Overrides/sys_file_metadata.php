<?php

defined('TYPO3') or die();

$tempColumns = [
    'ardsounds_thumbnail' => [
        'exclude' => true,
        'label' => 'ARD Sounds Thumbnail',
        'config' => [
            'type' => 'text',
            'cols' => 40,
            'rows' => 15,
            'eval' => 'trim',
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_file_metadata', $tempColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_metadata',
    'ardsounds_thumbnail'
);
