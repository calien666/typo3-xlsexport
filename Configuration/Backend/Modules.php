<?php

declare(strict_types=1);

defined('TYPO3') or die();

return [
    'web_xlsexport' => [
        'parent' => 'web',
        'position' => 'bottom',
        'access' => 'user,group',
        'iconIdentifier' => 'mimetypes-excel',
        'labels' => 'LLL:EXT:xlsexport/Resources/Private/Language/locallang_db.xlf',
        'extensionName' => 'Xlsexport',
        'controllerActions' => [
            \Calien\Xlsexport\Controller\XlsExportController::class => [
                'index',
                'export',
            ],
        ],
        'inheritNavigationComponentFromMainModule' => true,
    ],
];
