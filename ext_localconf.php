<?php

use TYPO3\CMS\Core\Core\Environment;

defined('TYPO3') or die();

(static function (): void {
    // Compatibility layer providing autoloading for the bundled PhpSpreadsheet library in classic
    // (non-Composer) installations prior to TYPO3 v14.3. From v14.3 on, the classic bootstrap uses
    // extra.typo3/cms.Package.providesPackages from composer.json to register the bundled library
    // early. In Composer installations PhpSpreadsheet comes from the project's own vendor directory,
    // so the guard keeps this from registering it a second time.
    // @todo typo3/cms:>=14.3 Remove this compatibility layer.
    if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
        require Environment::getExtensionsPath() . '/xlsexport/contrib/Libraries/autoload.php';
    }
})();
