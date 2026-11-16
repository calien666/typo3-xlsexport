<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Event\Query;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Dispatched before the record count of an export is queried for the overview of the backend module.
 *
 * Listeners add the same constraints and joins as in {@see ModifyExportQueryEvent}, so the overview counts what
 * is downloaded. Bind every value through `createNamedParameter()`. Do not add an `orderBy()`, as some database
 * platforms reject it on a count query.
 */
final class ModifyExportCountQueryEvent
{
    /**
     * @param array<array-key, mixed> $filter
     */
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly string $configurationKey,
        private readonly int $pageId,
        private readonly array $filter
    ) {}

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    public function getConfigurationKey(): string
    {
        return $this->configurationKey;
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
}
