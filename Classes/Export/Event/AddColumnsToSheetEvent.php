<?php

/**
 * Markus Hofmann
 * 12.10.21 23:02
 * churchevent
 */

declare(strict_types=1);

namespace Calien\Xlsexport\Export\Event;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class AddColumnsToSheetEvent
{
    /**
     * @var Worksheet
     */
    private Worksheet $sheet;
    /**
     * @var int
     */
    private int $colIndexer;
    /**
     * @var int
     */
    private int $currentRow;

    /**
     * @param Worksheet $sheet
     * @param int $colIndexer
     * @param int $currentRow
     */
    public function __construct(Worksheet $sheet, int $colIndexer, int $currentRow)
    {
        $this->sheet = $sheet;
        $this->colIndexer = $colIndexer;
        $this->currentRow = $currentRow;
    }

    /**
     * getSheet
     * returns the current worksheet to add new columns
     *
     * @return Worksheet
     */
    public function getSheet(): Worksheet
    {
        return $this->sheet;
    }

    /**
     * returns the current colIndexer for the next column to be written
     * can be manipulated
     * to get next Columns name, call ExportTrait::$cols[$colIndexer]
     *
     * @return int
     */
    public function getColIndexer(): int
    {
        return $this->colIndexer;
    }

    /**
     * returns the current row of the sheet, should not be manipulated to avoid overriding in next line
     * @return int
     */
    public function getCurrentRow(): int
    {
        return $this->currentRow;
    }
}
