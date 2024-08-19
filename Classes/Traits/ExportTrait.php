<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Traits;

use Calien\Xlsexport\Export\Event\AddColumnsToSheetEvent;
use Calien\Xlsexport\Export\Event\ManipulateCellDataEvent;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait ExportTrait
{
    protected $eventDispatcher;
    /**
     * @var array
     */
    public static array $cols = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ',
    ];
    /**
     * @var int
     */
    protected static int $rowCount = 0;
    /**
     * @var Spreadsheet|null
     */
    protected static ?Spreadsheet $spreadSheet = null;

    /**
     * loadSheet
     * @return Worksheet
     * @throws Exception
     */
    protected static function loadSheet(): Worksheet
    {
        self::$spreadSheet = new Spreadsheet();
        self::$spreadSheet->getProperties()->setCreator('TYPO3 Export')
            ->setLastModifiedBy('TYPO3 Export')
            ->setTitle('Export ' . ' Dokument')
            ->setSubject('Export ' . ' Dokument')
            ->setCreated(time())
            ->setDescription('Export ' . ' Dokument Quelle ');

        $sheet = self::$spreadSheet->setActiveSheetIndex(0);

        self::$rowCount = 1;

        return $sheet;
    }

    /**
     * writeHeader
     * @param Worksheet $sheet
     * @param array $headerFields
     */
    protected static function writeHeader(Worksheet $sheet, array $headerFields)
    {
        foreach ($headerFields as $field => $value) {
            $sheet->setCellValue(self::$cols[$field] . self::$rowCount, $value);
        }
        self::$rowCount++;
    }

    /**
     * writeExcel
     * @param Worksheet $sheet
     * @param array $dataset
     * @param array $exportFields
     * @param string $table
     * @param bool $autoFilter
     * @param array $hookArray @deprecated
     */
    protected static function writeExcel(
        Worksheet $sheet,
        array $dataset,
        array $exportFields,
        string $table = '',
        bool $autoFilter = false,
        array $hookArray = []
    ) {
        $data = [];
        foreach ($dataset as $item) {
            $data[] = $item;
        }

        foreach ($data as $currentData) {
            $colIndexer = 0;
            foreach ($exportFields as $colIndexer => $value) {
                $manipulateCellData = new ManipulateCellDataEvent($value, $currentData, $currentData[$value]);
                if (!empty($this)) {
                    $this->eventDispatcher->dispatch($manipulateCellData);
                }
                $sheet->setCellValue(self::$cols[$colIndexer] . self::$rowCount, $manipulateCellData->getValue());
            }
            $colIndexer++;
            if (!empty($this)) {
                $this->eventDispatcher->dispatch(new AddColumnsToSheetEvent($sheet, $colIndexer, self::$rowCount));
            }
            if (array_key_exists($table, $hookArray) && is_array($hookArray[$table])) {
                $colIndexer--;
                foreach ($hookArray[$table] as $classObj) {
                    $hookObj = GeneralUtility::makeInstance($classObj);
                    if (method_exists($hookObj, 'addColumns')) {
                        trigger_error(
                            'Usage of hooks inside XLS export is deprecated and will be removed in future versions. Use PSR-14 Event dispatching instead.',
                            E_USER_DEPRECATED
                        );
                        $hookObj->addColumns($sheet, self::class, $colIndexer, self::$rowCount);
                    }
                }
            }
            self::$rowCount++;
        }

        if ($autoFilter) {
            $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        }

        for ($i = 0; $i < count($exportFields); $i++) {
            $sheet->getColumnDimension(self::$cols[$i])->setAutoSize(true);
        }

        foreach ($sheet->getRowIterator() as $rowDimension) {
            self::_autofitRowHeight($rowDimension);
        }
    }

    /**
     * _autofitRowHeight
     * @param Row $row
     * @param int $rowPadding
     * @return Worksheet
     */
    private static function _autofitRowHeight(Row $row, int $rowPadding = 5): Worksheet
    {
        $ws = $row->getWorksheet();
        $cellIterator = $row->getCellIterator();
        $maxCellLines = 0; // Init

        // Find out max cell line count
        foreach ($cellIterator as $cell) {
            $lines = explode("\n", (string)$cell->getValue());
            $lineCount = 0;
            // Ignore empty lines
            foreach ($lines as &$ignored) {
                $lineCount++;
            }
            $maxCellLines = max($maxCellLines, $lineCount);
        }

        // Force minimum line height to 1
        $maxCellLines = max($maxCellLines, 1);

        // Adjust row height
        $rowDimension = $ws->getRowDimension($row->getRowIndex());
        $rowHeight = (15 * $maxCellLines) + $rowPadding; // XLSX_LINE_HEIGHT = 13
        $rowDimension->setRowHeight($rowHeight);
        return $ws;
    }
}
