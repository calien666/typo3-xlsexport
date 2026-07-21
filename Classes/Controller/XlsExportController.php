<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Controller;

use Calien\Xlsexport\Enum\ExportFormat;
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
use TYPO3\CMS\Backend\Routing\UriBuilder;
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
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly UriBuilder $uriBuilder
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
            'formats' => $this->availableFormats(),
            'exportUri' => (string)$this->uriBuilder->buildUriFromRoute(self::MODULE_NAME . '.export'),
        ]);

        return $moduleTemplate->renderResponse('XlsExport/Index');
    }

    /**
     * @throws ExportWithoutConfigurationException
     * @throws ConfigurationNotFoundException
     */
    public function export(ServerRequestInterface $request): ResponseInterface
    {
        $pageId = $this->requestParameter($request, 'id');
        $configurationKey = $this->requestParameter($request, 'configuration');
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
        $format = $this->resolveFormat($request, $presentation['format']);

        $exportDataQuery = $this->databaseQueryTypoScriptParser->buildQueryBuilderFromArray(
            $this->exportConfigurationValidator->validate($rawConfiguration)
        );
        $this->databaseQueryTypoScriptParser->replacePlaceholderWithCurrentId($exportDataQuery, $pageId);

        $spreadsheet = $this->spreadsheetWriteService->generateSpreadsheet(
            $exportDataQuery->executeQuery(),
            $presentation['fieldLabels'],
            $format->value,
            $configurationKey
        );

        return $this->streamResponse($spreadsheet, $format, $this->resolveFilename($request, $configurationKey, $format));
    }

    /**
     * @param array<int|string, mixed> $modTSconfig
     * @return array<int|string, array{label: string, count: int|false, suggestedFilename: string, defaultFormat: string}>
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
            $rawLabel = $configuration['label'] ?? null;
            $label = (is_string($rawLabel) && $rawLabel !== '') ? $rawLabel : $validated['table'];
            $datasets[$configName] = [
                'label' => $label,
                'count' => $countQuery->executeQuery()->fetchOne(),
                'suggestedFilename' => $this->sanitizeFilename($label),
                'defaultFormat' => $this->configuredFormat($configuration)->value,
            ];
        }

        return $datasets;
    }

    private function streamResponse(StreamInterface $spreadsheet, ExportFormat $format, string $filename): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse()
            ->withBody($spreadsheet)
            ->withHeader('Content-Type', $format->mimeType())
            ->withHeader('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
    }

    /**
     * @return array<value-of<ExportFormat>, string>
     */
    private function availableFormats(): array
    {
        $formats = [];
        foreach (ExportFormat::cases() as $format) {
            $formats[$format->value] = $format->label();
        }

        return $formats;
    }

    private function resolveFormat(ServerRequestInterface $request, string $configuredFormat): ExportFormat
    {
        $requested = $this->requestParameter($request, 'format');
        if (is_string($requested) && ($fromRequest = ExportFormat::tryFrom(strtolower($requested))) instanceof ExportFormat) {
            return $fromRequest;
        }

        return ExportFormat::tryFrom(strtolower($configuredFormat)) ?? ExportFormat::Xlsx;
    }

    /**
     * @param array<int|string, mixed> $configuration
     */
    private function configuredFormat(array $configuration): ExportFormat
    {
        $format = $configuration['format'] ?? null;

        return is_string($format) ? (ExportFormat::tryFrom(strtolower($format)) ?? ExportFormat::Xlsx) : ExportFormat::Xlsx;
    }

    private function resolveFilename(ServerRequestInterface $request, string $configurationKey, ExportFormat $format): string
    {
        $requested = $this->requestParameter($request, 'filename');
        $base = (is_string($requested) && trim($requested) !== '') ? $requested : $configurationKey;

        return $this->sanitizeFilename($base) . '.' . $format->fileExtension();
    }

    private function sanitizeFilename(string $name): string
    {
        $sanitized = trim((string)preg_replace('/[^\w\-. ]+/u', '_', $name));

        return $sanitized !== '' ? $sanitized : 'export';
    }

    private function requestParameter(ServerRequestInterface $request, string $name): mixed
    {
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody) && array_key_exists($name, $parsedBody)) {
            return $parsedBody[$name];
        }

        return $request->getQueryParams()[$name] ?? null;
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
