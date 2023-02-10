<?php

(static function () {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Xlsexport',
        'web',
        'xlsexport',
        'bottom',
        [
            \Calien\Xlsexport\Controller\XlsExportController::class => 'index,export',
        ],
        [
            'access' => 'user,group',
            'name' => 'web_xlsexport',
            'routeTarget' => \Calien\Xlsexport\Controller\XlsExportController::class . '::handleRequest',
            'iconIdentifier' => 'mimetypes-excel',
            'labels' => 'LLL:EXT:xlsexport/Resources/Private/Language/locallang_db.xlf',
            'inheritNavigationComponentFromMainModule' => true,
        ]
    );
})();
