<?php

declare(strict_types=1);
/**
 * Created by: markus
 * Created at: 25.03.20 18:37
 */
(function () {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerPageTSConfigFile(
        'xlsexport',
        'Configuration/TSconfig/Page/exampleExport.tsconfig',
        'Excel Exporter Basis Definition'
    );
})();
