:navigation-title: Developer

..  include:: /Includes.rst.txt

..  _developer:

=========
Developer
=========

..  contents:: Table of contents
    :local:

The extension dispatches :ref:`PSR-14 events <t3coreapi:EventDispatcher>` that let you shape the
backend module, the export queries and the generated spreadsheet without replacing the controller.
Register a listener with the :php:`\TYPO3\CMS\Core\Attribute\AsEventListener` attribute.

*   :ref:`developer-filter` adds a filter form to the module and applies it to list and download.
*   The three events in the namespace :php:`\Calien\Xlsexport\Event\Export` are dispatched by
    :php:`\Calien\Xlsexport\Service\SpreadsheetWriteService` while the spreadsheet is written.

..  _developer-filter:

Filtering exports
=================

A filter consists of three parts, none of which requires overriding the controller:

#.  A form in the module, rendered by overriding the empty ``Filter`` partial.
#.  :ref:`developer-module-view` to assign the options of the form.
#.  :ref:`developer-count-query` and :ref:`developer-export-query` to apply the submitted values to
    the record count in the overview and to the download.

The request parameter ``filter`` is reserved for this purpose. The module sanitizes its values to
strings and nested arrays of strings, hands them to the events and forwards them as hidden fields
with every download. Apply the same constraints in both query events, so the overview counts what
is downloaded.

Rendering the filter form
-------------------------

The module renders the partial ``Filter`` above the list of exports. It is empty by default.
Override it with the :ref:`backend template override <t3tsconfig:pagetemplates>` of page TSconfig, which
your extension can ship in :file:`Configuration/page.tsconfig`:

..  code-block:: typoscript
    :caption: EXT:my_extension/Configuration/page.tsconfig

    templates.calien/xlsexport.1731700000 = my-vendor/my-extension:Resources/Private/TemplateOverrides/Xlsexport

The partial then lives in
:file:`EXT:my_extension/Resources/Private/TemplateOverrides/Xlsexport/Partials/Filter.html`. It
receives all module variables, among them:

``filterUri``
    Module URL of the current page. Submit the form to it with ``method="post"``.

``filter``
    The currently applied filter values.

``pageId``, ``datasets``
    The current page and the listed exports.

..  code-block:: html
    :caption: EXT:my_extension/Resources/Private/TemplateOverrides/Xlsexport/Partials/Filter.html

    <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">
    <form method="post" action="{filterUri}" class="row g-3 align-items-end mb-3">
        <div class="col-auto">
            <label for="filter-location" class="form-label">Location</label>
            <select id="filter-location" name="filter[location]" class="form-select">
                <option value="">All</option>
                <f:for each="{locations}" as="location">
                    <option value="{location.uid}"
                        {f:if(condition: '{location.uid} == {filter.location}', then: 'selected')}>
                        {location.title}
                    </option>
                </f:for>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-default">Apply filter</button>
        </div>
    </form>
    </html>

..  _developer-module-view:

ModifyExportModuleViewEvent
===========================

:php:`\Calien\Xlsexport\Event\Module\ModifyExportModuleViewEvent` is dispatched before the
export overview of the module is rendered. Use it to assign the options of your filter form or to add
buttons to the document header.

:Mutable: the module template, via :php:`getModuleTemplate()`
:Read: :php:`getRequest()`, :php:`getPageId()`, :php:`getFilter()`, :php:`getDatasets()`

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/ProvideLocationFilter.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use Calien\Xlsexport\Event\Module\ModifyExportModuleViewEvent;
    use MyVendor\MyExtension\Domain\Repository\LocationRepository;
    use TYPO3\CMS\Core\Attribute\AsEventListener;

    final class ProvideLocationFilter
    {
        public function __construct(
            private readonly LocationRepository $locationRepository
        ) {}

        #[AsEventListener]
        public function __invoke(ModifyExportModuleViewEvent $event): void
        {
            $event->getModuleTemplate()->assign(
                'locations',
                $this->locationRepository->findByPage($event->getPageId())
            );
        }
    }

..  _developer-count-query:

ModifyExportCountQueryEvent
===========================

:php:`\Calien\Xlsexport\Event\Query\ModifyExportCountQueryEvent` is dispatched once per export
before its record count is queried for the overview of the module. Use it to add the constraints and
joins of your filter.

:Mutable: the query, via :php:`getQueryBuilder()`
:Read: :php:`getConfigurationKey()` (the export key), :php:`getPageId()`, :php:`getFilter()`

Bind every value through :php:`createNamedParameter()`. Do not add an :php:`orderBy()`, as some
database platforms reject it on a count query. See :ref:`developer-export-query` for an example.

..  _developer-export-query:

ModifyExportQueryEvent
======================

:php:`\Calien\Xlsexport\Event\Query\ModifyExportQueryEvent` is dispatched before the query of
a download is executed. Use it to add the constraints and joins of your filter, or an ordering.

:Mutable: the query, via :php:`getQueryBuilder()`
:Read: :php:`getConfigurationKey()` (the export key), :php:`getPageId()`, :php:`getFilter()`

Bind every value through :php:`createNamedParameter()`. A listener applying a filter usually handles
both query events, so the count in the overview matches the download:

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/ApplyLocationFilter.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use Calien\Xlsexport\Event\Query\ModifyExportCountQueryEvent;
    use Calien\Xlsexport\Event\Query\ModifyExportQueryEvent;
    use TYPO3\CMS\Core\Attribute\AsEventListener;
    use TYPO3\CMS\Core\Database\Connection;
    use TYPO3\CMS\Core\Database\Query\QueryBuilder;

    final class ApplyLocationFilter
    {
        #[AsEventListener]
        public function filterCount(ModifyExportCountQueryEvent $event): void
        {
            if ($event->getConfigurationKey() === 'events') {
                $this->constrainLocation($event->getQueryBuilder(), $event->getFilter());
            }
        }

        #[AsEventListener]
        public function filterExport(ModifyExportQueryEvent $event): void
        {
            if ($event->getConfigurationKey() === 'events') {
                $this->constrainLocation($event->getQueryBuilder(), $event->getFilter());
            }
        }

        private function constrainLocation(QueryBuilder $queryBuilder, array $filter): void
        {
            $location = (int)($filter['location'] ?? 0);
            if ($location === 0) {
                return;
            }
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    'location',
                    $queryBuilder->createNamedParameter($location, Connection::PARAM_INT)
                )
            );
        }
    }

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
