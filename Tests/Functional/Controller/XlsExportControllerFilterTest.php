<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Tests\Functional\Controller;

use Calien\Xlsexport\Controller\XlsExportController;
use Calien\Xlsexport\Event\Query\ModifyExportCountQueryEvent;
use Calien\Xlsexport\Event\Query\ModifyExportQueryEvent;
use Calien\XlsexportEventsTest\EventListener\ExportFilter;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class XlsExportControllerFilterTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'calien/xlsexport',
        'calien/xlsexport-events-test',
    ];

    private const TSCONFIG = <<<'TSCONFIG'
templates.calien/xlsexport.1731700000 = calien/xlsexport-events-test:Resources/Private/TemplateOverrides
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $this->get(ConnectionPool::class)->getConnectionForTable('pages')->insert('pages', [
            'uid' => 1,
            'pid' => 0,
            'title' => 'Home',
            'doktype' => 1,
            'TSconfig' => self::TSCONFIG,
        ]);
    }

    #[Test]
    public function indexRendersTheOverriddenFilterPartialWithListenerVariables(): void
    {
        $subject = $this->get(XlsExportController::class);

        $body = (string)$subject->index($this->moduleRequest(['id' => 1], ['filter' => ['header' => 'First']]))->getBody();

        $this->assertStringContainsString('<p class="fixture-filter">Filter on page 1: First</p>', $body);
    }

    #[Test]
    public function indexCountsOnlyTheFilteredRecords(): void
    {
        $subject = $this->get(XlsExportController::class);

        $body = (string)$subject->index($this->moduleRequest(['id' => 1], ['filter' => ['header' => 'First']]))->getBody();

        $this->assertMatchesRegularExpression('/>\s*1 Records\s*</', $body);
    }

    #[Test]
    public function indexCountsAllRecordsWithoutFilter(): void
    {
        $subject = $this->get(XlsExportController::class);

        $body = (string)$subject->index($this->moduleRequest(['id' => 1]))->getBody();

        $this->assertMatchesRegularExpression('/>\s*2 Records\s*</', $body);
    }

    #[Test]
    public function indexForwardsTheFilterIntoTheExportForm(): void
    {
        $subject = $this->get(XlsExportController::class);

        $body = (string)$subject->index($this->moduleRequest(['id' => 1], ['filter' => ['header' => 'First']]))->getBody();

        $this->assertStringContainsString('<input type="hidden" name="filter[header]" value="First"', $body);
    }

    #[Test]
    public function exportAppliesTheFilterToTheQuery(): void
    {
        $subject = $this->get(XlsExportController::class);

        $body = (string)$subject->export($this->moduleRequest([], [
            'id' => '1',
            'configuration' => 'content',
            'filter' => [
                'header' => 'First',
            ],
        ]))->getBody();

        $this->assertStringContainsString('"FIRST"', $body);
        $this->assertStringNotContainsString('"SECOND"', $body);
    }

    #[Test]
    public function indexDispatchesOnlyTheCountQueryEvent(): void
    {
        $subject = $this->get(XlsExportController::class);

        $subject->index($this->moduleRequest(['id' => 1]));

        $this->assertSame([ModifyExportCountQueryEvent::class], $this->get(ExportFilter::class)->getReceivedQueryEvents());
    }

    #[Test]
    public function exportDispatchesOnlyTheExportQueryEvent(): void
    {
        $subject = $this->get(XlsExportController::class);

        $subject->export($this->moduleRequest([], [
            'id' => '1',
            'configuration' => 'content',
        ]));

        $this->assertSame([ModifyExportQueryEvent::class], $this->get(ExportFilter::class)->getReceivedQueryEvents());
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $parsedBody
     */
    private function moduleRequest(array $queryParams, array $parsedBody = []): ServerRequest
    {
        $request = (new ServerRequest('https://example.com/typo3/module/web/xlsexport', 'POST'))
            ->withQueryParams($queryParams)
            ->withParsedBody($parsedBody);

        return $request
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('route', new Route('/xlsexport', ['packageName' => 'calien/xlsexport']));
    }
}
