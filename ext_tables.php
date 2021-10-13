<?php

(function () {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'web',
        'xlsexport',
        'bottom',
        null,
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
