<?php

declare(strict_types=1);

use Calien\Xlsexport\Controller\XlsExportController;

return [
    'web_xlsexport' => [
        'parent' => 'web',
        'position' => [
            'after' => 'web_list',
        ],
        'access' => 'user',
        'iconIdentifier' => 'mimetypes-excel',
        'labels' => 'LLL:EXT:xlsexport/Resources/Private/Language/locallang_db.xlf',
        'routes' => [
            '_default' => [
                'target' => XlsExportController::class . '::index',
            ],
            'export' => [
                'path' => '/export',
                'target' => XlsExportController::class . '::export',
            ],
        ],
    ],
];
