<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Controller;

use Calien\Xlsexport\Exception\ConfigurationNotFoundException;
use Calien\Xlsexport\Exception\ExportWithoutConfigurationException;
use Calien\Xlsexport\Service\DatabaseQueryTypoScriptParser;
use Calien\Xlsexport\Service\ExportConfigurationValidator;
use Calien\Xlsexport\Service\SpreadsheetWriteService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;

/**
 * Backend module listing the configured exports of the current page and streaming the chosen one.
 */
#[AsController]
final class XlsExportController
{
    private const MODULE_NAME = 'web_xlsexport';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly TypoScriptService $typoScriptService,
        private readonly DatabaseQueryTypoScriptParser $databaseQueryTypoScriptParser,
        private readonly ExportConfigurationValidator $exportConfigurationValidator,
        private readonly SpreadsheetWriteService $spreadsheetWriteService,
        private readonly ResponseFactoryInterface $responseFactory
    ) {}

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $pageId = (int)($request->getQueryParams()['id'] ?? 0);
        $modTSconfig = $this->loadTSconfig($pageId);

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'noConfig' => $modTSconfig === [],
            'datasets' => $this->buildDatasets($modTSconfig, $pageId),
            'pageId' => $pageId,
        ]);

        return $moduleTemplate->renderResponse('XlsExport/Index');
    }

    /**
     * @throws ExportWithoutConfigurationException
     * @throws ConfigurationNotFoundException
     */
    public function export(ServerRequestInterface $request): ResponseInterface
    {
        $pageId = $request->getQueryParams()['id'] ?? null;
        $configurationKey = $request->getQueryParams()['configuration'] ?? null;
        if ($pageId === null || $configurationKey === null) {
            throw new ExportWithoutConfigurationException(
                'For an export you need a valid configuration key',
                1731105142347
            );
        }
        $pageId = (int)$pageId;
        $configurationKey = (string)$configurationKey;

        $modTSconfig = $this->loadTSconfig($pageId);
        if (!array_key_exists($configurationKey, $modTSconfig) || !is_array($modTSconfig[$configurationKey])) {
            throw new ConfigurationNotFoundException(
                'Configuration not found for export on current page',
                1731105227250
            );
        }
        $rawConfiguration = $modTSconfig[$configurationKey];
        $presentation = $this->exportConfigurationValidator->validatePresentation($rawConfiguration);

        $exportDataQuery = $this->databaseQueryTypoScriptParser->buildQueryBuilderFromArray(
            $this->exportConfigurationValidator->validate($rawConfiguration)
        );
        $this->databaseQueryTypoScriptParser->replacePlaceholderWithCurrentId($exportDataQuery, $pageId);

        return $this->streamResponse(
            $this->spreadsheetWriteService->generateSpreadsheet(
                $exportDataQuery->executeQuery(),
                $presentation['fieldLabels'],
                $presentation['format'],
                $configurationKey
            )
        );
    }

    /**
     * @param array<int|string, mixed> $modTSconfig
     * @return array<int|string, array{label: string, count: int|false}>
     */
    private function buildDatasets(array $modTSconfig, int $pageId): array
    {
        $datasets = [];
        foreach ($modTSconfig as $configName => $configuration) {
            if (!is_array($configuration)) {
                continue;
            }
            $validated = $this->exportConfigurationValidator->validate($configuration);
            $countQuery = $this->databaseQueryTypoScriptParser->buildCountQueryFromArray($validated);
            $this->databaseQueryTypoScriptParser->replacePlaceholderWithCurrentId($countQuery, $pageId);
            $label = $configuration['label'] ?? null;
            $datasets[$configName] = [
                'label' => (is_string($label) && $label !== '') ? $label : $validated['table'],
                'count' => $countQuery->executeQuery()->fetchOne(),
            ];
        }

        return $datasets;
    }

    private function streamResponse(StreamInterface $spreadsheet): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse()
            ->withBody($spreadsheet)
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /**
     * @return array<int|string, mixed>
     */
    private function loadTSconfig(int $currentId): array
    {
        $tsConfig = BackendUtility::getPagesTSconfig($currentId);
        $moduleConfigArrayName = self::MODULE_NAME . '.';
        if (!array_key_exists($moduleConfigArrayName, $tsConfig['mod.'] ?? [])) {
            return [];
        }

        return $this->typoScriptService->convertTypoScriptArrayToPlainArray($tsConfig['mod.'][$moduleConfigArrayName]);
    }
}
