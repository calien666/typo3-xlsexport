<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Controller;

use Calien\Xlsexport\Exception\ConfigurationNotFoundException;
use Calien\Xlsexport\Exception\ExportWithoutConfigurationException;
use Calien\Xlsexport\Service\DatabaseQueryTypoScriptParser;
use Calien\Xlsexport\Service\SpreadsheetWriteService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;

#[AsController]
final class XlsExportController
{
    /**
     * @var array<non-empty-string, mixed>
     */
    protected array $modTSconfig = [];

    private readonly string $moduleName;
    protected ModuleTemplate $moduleTemplate;

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly TypoScriptService $typoScriptService,
        private readonly DatabaseQueryTypoScriptParser $databaseQueryTypoScriptParser,
        private readonly SpreadsheetWriteService $spreadsheetWriteService
    ) {
        $this->moduleName = 'web_xlsexport';
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($request);
        $pageId = (int)($request->getQueryParams()['id'] ?? null) ?? 0;
        $this->loadTSconfig($pageId);
        $assignedValues = [
            'noConfig' => $this->modTSconfig === [],
            'datasets' => [],
            'pageId' => $pageId,
        ];
        foreach ($this->modTSconfig as $configName => $configuration) {
            $countQuery = $this->databaseQueryTypoScriptParser->buildCountQueryFromArray($configuration);
            $this->databaseQueryTypoScriptParser->replacePlaceholderWithCurrentId($countQuery, $pageId);
            $assignedValues['datasets'][$configName] = [
                'label' => $configuration['label'] ?? $configuration['table'],
                'count' => $countQuery->executeQuery()->fetchOne(),
            ];
        }

        $this->moduleTemplate->assignMultiple($assignedValues);

        return $this->moduleTemplate->renderResponse('XlsExport/Index');
    }

    /**
     * @throws ExportWithoutConfigurationException
     * @throws ConfigurationNotFoundException
     */
    public function export(ServerRequestInterface $request): ResponseInterface
    {
        $pageId = $request->getQueryParams()['id'] ?? null;
        $configuration = $request->getQueryParams()['configuration'] ?? null;
        if ($pageId === null || $configuration === null) {
            throw new ExportWithoutConfigurationException(
                'For an export you need a valid configuration key',
                1731105142347
            );
        }
        $pageId = (int)$pageId;

        $this->loadTSconfig($pageId);
        if (!array_key_exists($configuration, $this->modTSconfig)) {
            throw new ConfigurationNotFoundException(
                'Configuration not found for export on current page',
                1731105227250
            );
        }

        $exportDataQuery = $this->databaseQueryTypoScriptParser->buildQueryBuilderFromArray($this->modTSconfig[$configuration]);
        $this->databaseQueryTypoScriptParser->replacePlaceholderWithCurrentId($exportDataQuery, $pageId);

        $result = $exportDataQuery->executeQuery();

        $spreadsheet = $this->spreadsheetWriteService->generateSpreadsheet($result, $this->modTSconfig[$configuration], $configuration);

        return (new ResponseFactory())
            ->createResponse()
            ->withBody($spreadsheet)
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    private function loadTSconfig(int $currentId): void
    {
        $TSconfig = BackendUtility::getPagesTSconfig($currentId);
        $moduleConfigArrayName = sprintf('%s.', $this->moduleName);
        if (array_key_exists($moduleConfigArrayName, $TSconfig['mod.'])) {
            $this->modTSconfig = $this->typoScriptService->convertTypoScriptArrayToPlainArray($TSconfig['mod.'][$moduleConfigArrayName]);
        }
    }
}
