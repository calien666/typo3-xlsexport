<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Traits;

use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Http\Message\StreamInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException;

trait ExportWithTsSettingsTrait
{
    use ExportTrait;

    /**
     * @var ConnectionPool|null
     */
    protected $dbConnection = null;

    protected $settings = [];

    protected $moduleName = 'tx_xlsexport';

    /**
     * @param int $currentId
     */
    protected function loadTSconfig(int $currentId)
    {
        $TSconfig = BackendUtility::getPagesTSconfig($currentId);
        $modTSconfig = $TSconfig['mod.'][$this->moduleName . '.'];

        if (is_array($this->settings) && !empty($this->settings)) {
            $this->settings = array_merge_recursive($this->settings, $this->modTSconfig['settings.']);
        } else {
            $this->settings = $modTSconfig['settings.'];
        }
    }

    /**
     * @param array $settings
     * @param int $currentId
     * @return StreamInterface
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    protected function doExport(array $settings, int $currentId) : StreamInterface
    {
        $hookArray = [];
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries'])) {
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
                    $exportQuery = $hookObj->alternateExportQuery($exportQuery, $this, $settings['value']);
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

        $fileStream = new Stream(GeneralUtility::tempnam('xlsexport_', '.xls'));
        $objWriter = IOFactory::createWriter($this->spreadSheet, 'Xls');
        $objWriter->save($fileStream);

        return $fileStream;
    }
}