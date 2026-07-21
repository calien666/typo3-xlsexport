<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Tests\Functional\Controller;

use Calien\Xlsexport\Controller\XlsExportController;
use Calien\Xlsexport\Exception\ConfigurationNotFoundException;
use Calien\Xlsexport\Exception\ExportWithoutConfigurationException;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class XlsExportControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['calien/xlsexport'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');
    }

    #[Test]
    public function exportWithoutConfigurationThrowsException(): void
    {
        $subject = $this->get(XlsExportController::class);

        $this->expectException(ExportWithoutConfigurationException::class);
        $this->expectExceptionCode(1731105142347);

        $subject->export($this->backendRequest());
    }

    #[Test]
    public function exportWithUnknownConfigurationThrowsException(): void
    {
        $subject = $this->get(XlsExportController::class);

        $this->expectException(ConfigurationNotFoundException::class);
        $this->expectExceptionCode(1731105227250);

        $subject->export($this->backendRequest(['id' => 1, 'configuration' => 'doesNotExist']));
    }

    #[Test]
    public function exportStreamsTheConfiguredData(): void
    {
        $this->createPageWithTSconfig($this->exampleTSconfig());
        $subject = $this->get(XlsExportController::class);

        $response = $subject->export($this->backendRequest(['id' => 1, 'configuration' => 'content']));
        $body = (string)$response->getBody();

        $this->assertStringContainsString('"First"', $body);
        $this->assertStringContainsString('"Second"', $body);
        $this->assertStringNotContainsString('"Third"', $body);
        $this->assertSame('text/csv', $response->getHeaderLine('Content-Type'));
        $this->assertSame('attachment; filename="content.csv"', $response->getHeaderLine('Content-Disposition'));
    }

    #[Test]
    public function exportRespectsRequestedFormatAndSanitizedFilename(): void
    {
        $this->createPageWithTSconfig($this->exampleTSconfig());
        $subject = $this->get(XlsExportController::class);
        $request = $this->backendRequest([
            'id' => 1,
            'configuration' => 'content',
            'format' => 'xlsx',
            'filename' => 'my/report',
        ]);

        $response = $subject->export($request);

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->getHeaderLine('Content-Type')
        );
        $this->assertSame('attachment; filename="my_report.xlsx"', $response->getHeaderLine('Content-Disposition'));
    }

    #[Test]
    public function indexSkipsInvalidConfigurationAndFlashesAWarning(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $this->createPageWithTSconfig($this->exampleTSconfig() . $this->brokenTSconfig());
        $subject = $this->get(XlsExportController::class);
        $request = $this->backendRequest(['id' => 1])
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('route', new Route('/xlsexport', ['packageName' => 'calien/xlsexport']));

        $body = (string)$subject->index($request)->getBody();

        // The invalid "broken" export is skipped and reported as a rendered warning,
        // while the valid "content" export is still listed.
        $this->assertStringContainsString('Skipped invalid export', $body);
        $this->assertStringContainsString('broken', $body);
        $this->assertStringContainsString('tt_content', $body);
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function backendRequest(array $queryParams = []): ServerRequest
    {
        $request = (new ServerRequest())->withQueryParams($queryParams);

        return $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
    }

    private function createPageWithTSconfig(string $tsConfig): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('pages')->insert('pages', [
            'uid' => 1,
            'pid' => 0,
            'title' => 'Home',
            'doktype' => 1,
            'TSconfig' => $tsConfig,
        ]);
    }

    private function brokenTSconfig(): string
    {
        return <<<'TSCONFIG'

mod.web_xlsexport.broken {
    select {
        10 = uid
    }
}
TSCONFIG;
    }

    private function exampleTSconfig(): string
    {
        return <<<'TSCONFIG'
mod.web_xlsexport.content {
    table = tt_content
    format = csv
    select {
        10 = uid
        20 = header
    }
    fieldLabels {
        10 = ID
        20 = Header
    }
    count = *
    where {
        10 {
            fieldName = pid
            parameter = ###CURRENT_ID###
            expressionType = eq
            type = int
        }
    }
}
TSCONFIG;
    }
}
