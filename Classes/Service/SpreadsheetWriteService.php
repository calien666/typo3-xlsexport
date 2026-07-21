<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Service;

use Calien\Xlsexport\Enum\ExportFormat;
use Calien\Xlsexport\Event\Export\AlternateFirstColumnInSheetEvent;
use Calien\Xlsexport\Event\Export\AlternateHeaderLineEvent;
use Calien\Xlsexport\Event\Export\ManipulateRowEntryEvent;
use Calien\Xlsexport\Exception\ExportFormatNotDetectedException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Http\Stream;

/**
 * Streams a database result into a spreadsheet in any format supported by PhpSpreadsheet.
 *
 * @internal
 */
final class SpreadsheetWriteService
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * @param array<array-key, non-empty-string> $fieldLabels
     * @param non-empty-string $format
     * @throws ExportFormatNotDetectedException
     * @throws Exception
     */
    public function generateSpreadsheet(Result $result, array $fieldLabels, string $format, string $configurationKey): Stream
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->setActiveSheetIndex(0);

        /** @var AlternateFirstColumnInSheetEvent $alternateFirstColumnEvent */
        $alternateFirstColumnEvent = $this->eventDispatcher->dispatch(new AlternateFirstColumnInSheetEvent());
        $firstColumn = $alternateFirstColumnEvent->getFirstColumn();

        /** @var AlternateHeaderLineEvent $alternateHeaderLineEvent */
        $alternateHeaderLineEvent = $this->eventDispatcher->dispatch(new AlternateHeaderLineEvent($fieldLabels, $configurationKey));
        $headerFieldLabels = $alternateHeaderLineEvent->getHeaderFieldLabels();
        $sheet->fromArray($headerFieldLabels, null, $firstColumn . '1');
        while ($dataRow = $result->fetchAssociative()) {
            $row = $sheet->getHighestRow() + 1;

            /** @var ManipulateRowEntryEvent $manipulateRowEvent */
            $manipulateRowEvent = $this->eventDispatcher->dispatch(new ManipulateRowEntryEvent($dataRow, $headerFieldLabels, $configurationKey));
            $sheet->fromArray($manipulateRowEvent->getRow(), null, $firstColumn . $row);
        }

        $iWriter = IOFactory::createWriter(
            $spreadsheet,
            $this->resolveWriterType($format)
        );

        $resource = fopen('php://memory', 'w');
        if (!is_resource($resource)) {
            throw new \RuntimeException(
                'Can not create resource for spreadsheet writer',
                1731108793376
            );
        }
        $iWriter->save($resource);

        return new Stream($resource);
    }

    /**
     * @throws ExportFormatNotDetectedException
     */
    private function resolveWriterType(string $format): string
    {
        $exportFormat = ExportFormat::tryFrom(strtolower($format));
        if ($exportFormat === null) {
            throw new ExportFormatNotDetectedException(
                sprintf('The export format "%s" is not supported.', $format),
                1731106070328
            );
        }

        return $exportFormat->toWriterType();
    }
}
