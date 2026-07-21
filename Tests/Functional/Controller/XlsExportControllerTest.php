<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Tests\Functional\Controller;

use Calien\Xlsexport\Controller\XlsExportController;
use Calien\Xlsexport\Exception\ConfigurationNotFoundException;
use Calien\Xlsexport\Exception\ExportWithoutConfigurationException;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
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

        $subject->export(new ServerRequest());
    }

    #[Test]
    public function exportWithUnknownConfigurationThrowsException(): void
    {
        $subject = $this->get(XlsExportController::class);
        $request = (new ServerRequest())->withQueryParams(['id' => 1, 'configuration' => 'doesNotExist']);

        $this->expectException(ConfigurationNotFoundException::class);
        $this->expectExceptionCode(1731105227250);

        $subject->export($request);
    }

    #[Test]
    public function exportStreamsTheConfiguredData(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['BE']['defaultPageTSconfig'] .= $this->exampleTSconfig();
        $subject = $this->get(XlsExportController::class);
        $request = (new ServerRequest())->withQueryParams(['id' => 1, 'configuration' => 'content']);

        $response = $subject->export($request);
        $body = (string)$response->getBody();

        $this->assertStringContainsString('"First"', $body);
        $this->assertStringContainsString('"Second"', $body);
        $this->assertStringNotContainsString('"Third"', $body);
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
