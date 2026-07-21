<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Enum;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * The spreadsheet formats an export may be written to, selectable by keyword in TSconfig
 * (e.g. `format = xlsx`) and in the module. Each case owns its PhpSpreadsheet writer, the download
 * MIME type and the file extension. Deliberately excludes HTML and the PDF writers (the latter need
 * libraries this extension does not depend on).
 */
enum ExportFormat: string
{
    case Xlsx = 'xlsx';
    case Xls = 'xls';
    case Ods = 'ods';
    case Csv = 'csv';

    public function toWriterType(): string
    {
        return match ($this) {
            self::Xlsx => IOFactory::WRITER_XLSX,
            self::Xls => IOFactory::WRITER_XLS,
            self::Ods => IOFactory::WRITER_ODS,
            self::Csv => IOFactory::WRITER_CSV,
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Xls => 'application/vnd.ms-excel',
            self::Ods => 'application/vnd.oasis.opendocument.spreadsheet',
            self::Csv => 'text/csv',
        };
    }

    public function fileExtension(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Xlsx => 'Excel 2007+ (.xlsx)',
            self::Xls => 'Excel 97-2003 (.xls)',
            self::Ods => 'OpenDocument (.ods)',
            self::Csv => 'CSV (.csv)',
        };
    }
}
