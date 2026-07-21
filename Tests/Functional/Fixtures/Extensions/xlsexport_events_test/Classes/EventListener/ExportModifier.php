<?php

declare(strict_types=1);

namespace Calien\XlsexportEventsTest\EventListener;

use Calien\Xlsexport\Event\Export\AlternateFirstColumnInSheetEvent;
use Calien\Xlsexport\Event\Export\AlternateHeaderLineEvent;
use Calien\Xlsexport\Event\Export\ManipulateRowEntryEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Exercises all three export events so a functional test can prove they alter the generated sheet.
 */
final class ExportModifier
{
    #[AsEventListener]
    public function moveFirstColumn(AlternateFirstColumnInSheetEvent $event): void
    {
        $event->setFirstColumn('B');
    }

    #[AsEventListener]
    public function prefixHeaderLabels(AlternateHeaderLineEvent $event): void
    {
        $event->setHeaderFieldLabels(
            array_map(static fn(string $label): string => 'X-' . $label, $event->getHeaderFieldLabels())
        );
    }

    #[AsEventListener]
    public function upperCaseRowValues(ManipulateRowEntryEvent $event): void
    {
        $event->setRow(
            array_map(static fn(mixed $value): mixed => is_string($value) ? strtoupper($value) : $value, $event->getRow())
        );
    }
}
