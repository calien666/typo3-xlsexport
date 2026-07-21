<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Tests\Functional\Service;

use Calien\Xlsexport\Service\SpreadsheetWriteService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SpreadsheetWriteServiceEventsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'calien/xlsexport',
        'calien/xlsexport-events-test',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');
    }

    #[Test]
    public function listenersAlterFirstColumnHeaderAndRows(): void
    {
        $subject = $this->get(SpreadsheetWriteService::class);
        $result = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tt_content')
            ->select('uid', 'header')
            ->from('tt_content')
            ->orderBy('uid')
            ->executeQuery();

        $stream = $subject->generateSpreadsheet($result, ['ID', 'Header'], 'xlsx', 'content');

        $tempFile = GeneralUtility::tempnam('xlsexport_events_test_', '.xlsx');
        file_put_contents($tempFile, (string)$stream);
        $sheet = IOFactory::load($tempFile)->getActiveSheet();

        // AlternateFirstColumnInSheetEvent moved the output to column B, leaving column A empty.
        $this->assertNull($sheet->getCell('A1')->getValue());
        // AlternateHeaderLineEvent prefixed the field labels.
        $this->assertSame('X-ID', $sheet->getCell('B1')->getValue());
        $this->assertSame('X-Header', $sheet->getCell('C1')->getValue());
        // ManipulateRowEntryEvent upper-cased the row values.
        $this->assertSame('FIRST', $sheet->getCell('C2')->getValue());
        $this->assertSame('SECOND', $sheet->getCell('C3')->getValue());

        GeneralUtility::unlink_tempfile($tempFile);
    }
}
