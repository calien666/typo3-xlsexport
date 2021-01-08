<?php

declare(strict_types=1);
/**
 * Created by: markus
 * Created at: 25.03.20 18:37
 */

call_user_func(function () {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerPageTSConfigFile(
        'xlsexport',
        'Configuration/PageTSconfig/page.tsconfig',
        'Excel Exporter Basis Definition'
    );
});
