<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Tests\Functional\Service;

use Calien\Xlsexport\Exception\ExportFormatNotDetectedException;
use Calien\Xlsexport\Service\SpreadsheetWriteService;
use Doctrine\DBAL\Result;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SpreadsheetWriteServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['calien/xlsexport'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');
    }

    #[Test]
    public function csvExportContainsHeaderAndDataRows(): void
    {
        $subject = $this->get(SpreadsheetWriteService::class);
        $stream = $subject->generateSpreadsheet(
            $this->contentResult(),
            ['ID', 'Header'],
            'csv',
            'content'
        );

        $csv = (string)$stream;
        $this->assertStringContainsString('"ID","Header"', $csv);
        $this->assertStringContainsString('"1","First"', $csv);
        $this->assertStringContainsString('"2","Second"', $csv);
        $this->assertStringContainsString('"3","Third"', $csv);
    }

    #[Test]
    public function xlsxExportCanBeReadBackWithExpectedCells(): void
    {
        $subject = $this->get(SpreadsheetWriteService::class);
        $stream = $subject->generateSpreadsheet(
            $this->contentResult(),
            ['ID', 'Header'],
            'xlsx',
            'content'
        );

        $tempFile = GeneralUtility::tempnam('xlsexport_test_', '.xlsx');
        file_put_contents($tempFile, (string)$stream);
        $sheet = IOFactory::load($tempFile)->getActiveSheet();

        $this->assertSame('ID', $sheet->getCell('A1')->getValue());
        $this->assertSame('Header', $sheet->getCell('B1')->getValue());
        $this->assertSame('First', $sheet->getCell('B2')->getValue());
        $this->assertSame('Second', $sheet->getCell('B3')->getValue());
        $this->assertSame('Third', $sheet->getCell('B4')->getValue());
        $this->assertSame(4, $sheet->getHighestRow());

        GeneralUtility::unlink_tempfile($tempFile);
    }

    #[Test]
    public function unknownFormatThrowsException(): void
    {
        $subject = $this->get(SpreadsheetWriteService::class);

        $this->expectException(ExportFormatNotDetectedException::class);
        $this->expectExceptionCode(1731106070328);

        $subject->generateSpreadsheet(
            $this->contentResult(),
            ['ID'],
            'notAFormat',
            'content'
        );
    }

    private function contentResult(): Result
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tt_content');

        return $queryBuilder
            ->select('uid', 'header')
            ->from('tt_content')
            ->orderBy('uid')
            ->executeQuery();
    }
}
