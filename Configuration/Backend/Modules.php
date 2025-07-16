<?php

declare(strict_types=1);

use Calien\Xlsexport\Controller\XlsExportController;

return [
    'web_xlsexport' => [
        'routes' => [
            '_default' => [
                'target' => XlsExportController::class . '::index',
            ],
            'export' => [
                'path' => '/export',
                'target' => XlsExportController::class . '::export',
            ],
        ],
        'parent' => 'web',
        'access' => 'user',
        'position' => [
            'after' => 'web_list',
        ],
        'iconIdentifier' => 'mimetypes-excel',
        'labels' => 'LLL:EXT:xlsexport/Resources/Private/Language/locallang_db.xlf',
    ],
];
