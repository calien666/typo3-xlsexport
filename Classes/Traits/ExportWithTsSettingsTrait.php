<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Traits;

use Doctrine\DBAL\Driver\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Http\Message\StreamInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait ExportWithTsSettingsTrait
{
    use ExportTrait;

    /**
     * @var array
     */
    protected array $selfSettings = [];
    /**
     * @var string
     */
    protected string $moduleName = 'tx_xlsexport';
    /**
     * @var array
     */
    protected array $modTSconfig;

    /**
     * @param int $currentId
     */
    protected function loadTSconfig(int $currentId)
    {
        $TSconfig = BackendUtility::getPagesTSconfig($currentId);
        $moduleConfigArrayName = sprintf('%s.', $this->moduleName);
        if (array_key_exists($moduleConfigArrayName, $TSconfig['mod.'])) {
            $this->modTSconfig = $TSconfig['mod.'][$moduleConfigArrayName];
            $this->selfSettings = array_merge_recursive($this->selfSettings, $this->modTSconfig['settings.']);
        }
    }

    /**
     * doExport
     * @param array $settings
     * @param int $currentId
     * @return StreamInterface
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    protected function doExport(array $settings, int $currentId): StreamInterface
    {
        $this->normalizeSettings($settings);
        $hookArray = [];
        if (
            array_key_exists('xlsexport', $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'])
            && array_key_exists('alternateQueries', $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport'])
            && is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries'])
        ) {
            $hookArray = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries'];
        }

        $exportfieldnames = [];
        $exportfields = [];

        foreach ($settings['exportfields.'] as $value) {
            $exportfields[] = $value;
        }
        foreach ($settings['exportfieldnames.'] as $value) {
            $exportfieldnames[] = $value;
        }
        $exportQuery = $settings['export'];
        if (array_key_exists($settings['table'], $hookArray) && is_array($hookArray[$settings['table']])) {
            foreach ($hookArray[$settings['table']] as $classObj) {
                $hookObj = GeneralUtility::makeInstance($classObj);
                if (method_exists($hookObj, 'alternateExportQuery')) {
                    trigger_error(
                        'Usage of hooks inside XLS export is deprecated and will be removed in future versions. Use PSR-14 Event dispatching instead.',
                        E_USER_DEPRECATED
                    );
                    $exportQuery = $hookObj->alternateExportQuery($exportQuery, $this, '');
                }
            }
        }

        $statement = sprintf($exportQuery, $currentId);
        $dbQuery = $this->dbConnection->getQueryBuilderForTable($settings['table'])->getConnection();
        $result = $dbQuery->executeQuery($statement)->fetchAllAssociative();

        $sheet = $this->loadSheet();

        $this->rowCount = 1;

        $headerManipulated = false;
        if (array_key_exists($settings['table'], $hookArray) && is_array($hookArray[$settings['table']])) {
            foreach ($hookArray[$settings['table']] as $classObj) {
                $hookObj = GeneralUtility::makeInstance($classObj);
                if (method_exists($hookObj, 'alternateHeaderLine')) {
                    trigger_error(
                        'Usage of hooks inside XLS export is deprecated and will be removed in future versions. Use PSR-14 Event dispatching instead.',
                        E_USER_DEPRECATED
                    );
                    $hookObj->alternateHeaderLine($sheet, $this, $exportfieldnames, $this->rowCount);
                    $headerManipulated = true;
                }
            }
        }

        if (!$headerManipulated) {
            // Zeile mit den Spaltenbezeichungen
            $this->writeHeader($sheet, $exportfieldnames);
        }

        // Die Datensätze eintragen

        $this->writeExcel($sheet, $result, $exportfields, $settings['table'], (bool)$settings['autofilter'], $hookArray);

        $tempFile = GeneralUtility::tempnam('xlsexport_', '.xlsx');
        $objWriter = IOFactory::createWriter(self::$spreadSheet, 'Xlsx');
        $objWriter->save($tempFile);
        return new Stream($tempFile);
    }

    private function normalizeSettings(array &$settings): void
    {
        if (!array_key_exists('autofilter', $settings)) {
            $settings['autofilter'] = false;
        }
    }
}
