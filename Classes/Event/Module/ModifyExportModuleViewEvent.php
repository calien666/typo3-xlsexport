<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Event\Module;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;

/**
 * Dispatched before the export overview of the backend module is rendered.
 *
 * Listeners assign additional variables to the module template, e.g. the options of a filter form rendered by
 * an overridden `Filter` partial, or add buttons to the document header.
 */
final class ModifyExportModuleViewEvent
{
    /**
     * @param array<array-key, mixed> $filter
     * @param array<int|string, array{label: string, count: int|false, suggestedFilename: string, defaultFormat: string}> $datasets
     */
    public function __construct(
        private readonly ModuleTemplate $moduleTemplate,
        private readonly ServerRequestInterface $request,
        private readonly int $pageId,
        private readonly array $filter,
        private readonly array $datasets
    ) {}

    public function getModuleTemplate(): ModuleTemplate
    {
        return $this->moduleTemplate;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getPageId(): int
    {
        return $this->pageId;
    }

    /**
     * @return array<array-key, mixed> String values and nested arrays of string values
     */
    public function getFilter(): array
    {
        return $this->filter;
    }

    /**
     * @return array<int|string, array{label: string, count: int|false, suggestedFilename: string, defaultFormat: string}>
     */
    public function getDatasets(): array
    {
        return $this->datasets;
    }
}
