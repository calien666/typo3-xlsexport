<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Traits;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait ExportTrait
{
    /**
     * @var array
     */
    protected array $cols = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ',
    ];
    /**
     * @var int
     */
    protected int $rowCount = 0;
    /**
     * @var Spreadsheet|null
     */
    protected ?Spreadsheet $spreadSheet = null;

    protected function loadSheet(): Worksheet
    {
        $this->spreadSheet = new Spreadsheet();
        $this->spreadSheet->getProperties()->setCreator("TYPO3 Export")
            ->setLastModifiedBy("TYPO3 Export")
            ->setTitle("Export " . " Dokument")
            ->setSubject("Export " . " Dokument")
            ->setCreated(time())
            ->setDescription("Export " . " Dokument Quelle ");

        $sheet = $this->spreadSheet->setActiveSheetIndex(0);

        $this->rowCount = 1;

        return $sheet;
    }

    protected function writeHeader(Worksheet $sheet, array $headerFields)
    {
        foreach ($headerFields as $field => $value) {
            $sheet->setCellValue($this->cols[$field] . $this->rowCount, $value);
        }
        $this->rowCount++;
    }

    protected function writeExcel(
        Worksheet $sheet,
        array $dataset,
        array $exportFields,
        string $table = '',
        bool $autoFilter = false,
        array $hookArray = []
    )
    {
        $data = [];
        foreach ($dataset as $item) {
            $data[] = $item;
        }

        foreach ($data as $currentData) {
            foreach ($exportFields as $field => $value) {
                $sheet->setCellValue($this->cols[$field] . $this->rowCount, $currentData[$value]);
            }

            if (array_key_exists($table, $hookArray) && is_array($hookArray[$table])) {
                foreach ($hookArray[$table] as $classObj) {
                    $hookObj = GeneralUtility::makeInstance($classObj);
                    if (method_exists($hookObj, 'addColumns')) {
                        $hookObj->addColumns($sheet, $this, $field, $this->rowCount);
                    }
                }
            }
            $this->rowCount++;
        }

        if ($autoFilter) {
            $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        }

        for ($i = 0; $i < count($exportFields); $i++) {
            $sheet->getColumnDimension($this->cols[$i])->setAutoSize(true);
        }

        foreach ($sheet->getRowIterator() as $rowDimension) {
            $this->_autofitRowHeight($rowDimension);
        }
    }

    private function _autofitRowHeight(Row &$row, $rowPadding = 5)
    {
        $ws = $row->getWorksheet();
        $cellIterator = $row->getCellIterator();
        $maxCellLines = 0; // Init

        // Find out max cell line count
        foreach ($cellIterator as $cell) {
            $lines = explode("\n", (string)$cell->getValue());
            $lineCount = 0;
            // Ignore empty lines
            foreach ($lines as $idx => &$line) {
                $lineCount++;
                if (0 !== strlen(trim($line, " \t\n\r\0\x0B"))) {
                }
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
