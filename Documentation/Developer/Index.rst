:navigation-title: Developer

..  include:: /Includes.rst.txt

..  _developer:

=========
Developer
=========

..  contents:: Table of contents
    :local:

While the spreadsheet is generated, the extension dispatches three
:ref:`PSR-14 events <t3coreapi:EventDispatcher>` that let you shape the output without replacing the
writer. All three live in the namespace :php:`\Calien\Xlsexport\Event\Export` and are dispatched by
:php:`\Calien\Xlsexport\Service\SpreadsheetWriteService`. Register a listener with the
:php:`\TYPO3\CMS\Core\Attribute\AsEventListener` attribute.

..  _developer-first-column:

AlternateFirstColumnInSheetEvent
================================

Dispatched once, before anything is written, to decide the first spreadsheet column the header and
rows start in. The default is ``A``. Use it to leave room for a logo or leading columns your own
listener fills.

:Mutable: yes, via :php:`setFirstColumn(string $firstColumn)`

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/MoveExportStart.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use Calien\Xlsexport\Event\Export\AlternateFirstColumnInSheetEvent;
    use TYPO3\CMS\Core\Attribute\AsEventListener;

    final class MoveExportStart
    {
        #[AsEventListener]
        public function __invoke(AlternateFirstColumnInSheetEvent $event): void
        {
            $event->setFirstColumn('B');
        }
    }

..  _developer-header-line:

AlternateHeaderLineEvent
========================

Dispatched before the header row is written, carrying the field labels from ``fieldLabels``
and the export key. Use it to translate, reorder or extend the column headers.

:Mutable: yes, via :php:`setHeaderFieldLabels(array $headerFieldLabels)`
:Read: :php:`getHeaderFieldLabels()`, :php:`getConfiguration()` (the export key)

..  code-block:: php

    #[AsEventListener]
    public function __invoke(AlternateHeaderLineEvent $event): void
    {
        if ($event->getConfiguration() === 'orders') {
            $event->setHeaderFieldLabels(['#', 'Order number', 'Total']);
        }
    }

..  _developer-row-entry:

ManipulateRowEntryEvent
=======================

Dispatched for every data row before it is written, carrying the raw row (as fetched from the
database), the header labels and the export key. Use it to format values, resolve foreign keys to
readable labels, or add computed columns.

:Mutable: yes, via :php:`setRow(array $row)`
:Read: :php:`getRow()`, :php:`getFieldLabels()`, :php:`getConfigurationKey()`

..  code-block:: php

    #[AsEventListener]
    public function __invoke(ManipulateRowEntryEvent $event): void
    {
        $row = $event->getRow();
        $row['created'] = date('Y-m-d', (int)($row['created'] ?? 0));
        $event->setRow($row);
    }
