<?php

declare(strict_types=1);

namespace Calien\XlsexportEventsTest\EventListener;

use Calien\Xlsexport\Event\Module\ModifyExportModuleViewEvent;
use Calien\Xlsexport\Event\Query\ModifyExportCountQueryEvent;
use Calien\Xlsexport\Event\Query\ModifyExportQueryEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Exercises the module and query events so a functional test can prove a filter reaches list and download.
 */
final class ExportFilter
{
    /**
     * @var list<class-string>
     */
    private array $receivedQueryEvents = [];

    #[AsEventListener]
    public function filterCountByHeader(ModifyExportCountQueryEvent $event): void
    {
        $this->receivedQueryEvents[] = $event::class;
        $this->constrainHeader($event->getQueryBuilder(), $event->getFilter());
    }

    #[AsEventListener]
    public function filterExportByHeader(ModifyExportQueryEvent $event): void
    {
        $this->receivedQueryEvents[] = $event::class;
        $this->constrainHeader($event->getQueryBuilder(), $event->getFilter());
    }

    #[AsEventListener]
    public function assignFilterHeadline(ModifyExportModuleViewEvent $event): void
    {
        $event->getModuleTemplate()->assign('filterHeadline', sprintf('Filter on page %d', $event->getPageId()));
    }

    /**
     * @return list<class-string>
     */
    public function getReceivedQueryEvents(): array
    {
        return $this->receivedQueryEvents;
    }

    /**
     * @param array<array-key, mixed> $filter
     */
    private function constrainHeader(QueryBuilder $queryBuilder, array $filter): void
    {
        $header = $filter['header'] ?? null;
        if (!is_string($header) || $header === '') {
            return;
        }
        $queryBuilder->andWhere(
            $queryBuilder->expr()->eq('header', $queryBuilder->createNamedParameter($header, Connection::PARAM_STR))
        );
    }
}
