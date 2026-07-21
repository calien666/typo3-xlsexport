:navigation-title: Configuration

..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

Exports are configured in page TSconfig below ``mod.web_xlsexport``. Every direct sub-key is one
export offered by the backend module; the key is also used to reference the export internally:

..  code-block:: typoscript
    :caption: EXT:my_extension/Configuration/page.tsconfig

    mod.web_xlsexport {
        # "orders" is the export key
        orders {
            label = Orders of this page
            table = tx_myext_order
            select {
                10 = uid
                20 = order_number
            }
            fieldLabels {
                10 = ID
                20 = Order number
            }
        }
    }

Each definition is translated one-to-one into a TYPO3
:ref:`QueryBuilder <t3coreapi:database-query-builder>` query: values are bound with
:php:`QueryBuilder::createNamedParameter()` and column names are quoted with
:php:`QueryBuilder::quoteIdentifier()`.

..  note::

    The record count shown next to each export in the module is derived automatically from the same
    definition — the export's ``table``, filters and joins are reused — so no separate counting query
    is configured.

..  toctree::
    :maxdepth: 1
    :titlesonly:

    ExportOptions
    Filtering
    Joins
    Example
