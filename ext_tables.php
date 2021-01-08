<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}


// Backend Modules
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
    'SUDHAUS7.xlsexport',
    'web',
    'xlsexport',
    '',
    [
        'Xlsexport' => 'index, export'
    ],
    [
        'access' => 'user,group',
        'icon'   => 'EXT:xlsexport/Resources/Public/Icons/xlsdown.svg',
        'labels' => 'LLL:EXT:xlsexport/Resources/Private/Language/locallang_db.xlf',
    ]
);


\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('
<INCLUDE_TYPOSCRIPT: source="FILE:EXT:xlsexport/Configuration/PageTSconfig/page.ts">
');
