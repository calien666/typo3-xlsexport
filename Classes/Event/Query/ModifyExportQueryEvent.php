<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Event\Query;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Dispatched before the query of a download is executed.
 *
 * Listeners add constraints, joins or an ordering to the query builder, typically based on the filter values
 * submitted with the reserved `filter` request parameter. Bind every value through `createNamedParameter()`.
 * Apply the same constraints in {@see ModifyExportCountQueryEvent}, so the overview counts what is downloaded.
 */
final class ModifyExportQueryEvent
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
