<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Service;

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
     * @param array{
     *     select: non-empty-string[],
     *     format?: non-empty-string,
     *     fieldLabels: non-empty-string[]
     * } $configuration
     * @throws ExportFormatNotDetectedException
     * @throws Exception
     */
    public function generateSpreadsheet(Result $result, array $configuration, string $configurationKey): Stream
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->setActiveSheetIndex(0);

        /** @var AlternateFirstColumnInSheetEvent $alternateFirstColumnEvent */
        $alternateFirstColumnEvent = $this->eventDispatcher->dispatch(new AlternateFirstColumnInSheetEvent());
        $firstColumn = $alternateFirstColumnEvent->getFirstColumn();

        /** @var AlternateHeaderLineEvent $alternateHeaderLineEvent */
        $alternateHeaderLineEvent = $this->eventDispatcher->dispatch(new AlternateHeaderLineEvent($configuration['fieldLabels'], $configurationKey));
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
            $this->resolveFormatToWriterConstant($configuration['format'] ?? 'xlsx')
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
    private function resolveFormatToWriterConstant(string $format): string
    {
        $detectConstant = sprintf('WRITER_%s', mb_strtoupper($format));
        $reflection = new \ReflectionClass(IOFactory::class);
        $writerType = $reflection->getConstant($detectConstant);
        if (!is_string($writerType)) {
            throw new ExportFormatNotDetectedException(
                sprintf('The export format for file format "%s" was not found.', $format),
                1731106070328
            );
        }

        return $writerType;
    }
}
