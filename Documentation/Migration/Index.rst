:navigation-title: Migration

..  include:: /Includes.rst.txt

..  _migration:

=========================
Migration from version 4
=========================

..  contents:: Table of contents
    :local:

Version 5 is a hard break. There is no automatic upgrade path, because the SQL statements of a
version 4 export cannot be translated to the declarative format without knowing their intent. Read the
:ref:`breaking changes <migration-breaking>`, then :ref:`rewrite each export <migration-rewrite>`.

..  _migration-breaking:

Breaking changes
================

..  warning::

    Existing exports stop working after the upgrade until they are rewritten.

Supported versions
------------------

TYPO3 12 support is dropped. Version 5 requires TYPO3 13.4 LTS or 14.3 LTS and PHP 8.2 to 8.5, and
depends on `PhpSpreadsheet <https://phpspreadsheet.readthedocs.io/>`__ 3.

Export format and TSconfig namespace
------------------------------------

The export format changed completely. The ``check``, ``list`` and ``export`` SQL statements are
gone; an export is now the declarative definition described in :ref:`configuration`. The TSconfig
namespace also changed:

..  list-table::
    :header-rows: 1

    *   -   Version 4
        -   Version 5
    *   -   ``mod.tx_xlsexport.settings.exports.<key>``
        -   ``mod.web_xlsexport.<key>``

Extension points
----------------

The Extbase controller was replaced by a plain backend controller. The legacy
:php:`$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['xlsexport']['alternateQueries']` hooks and the old
events (``AddColumnsToSheetEvent``, ``AlternateCheckQueryEvent``, ``AlternateExportQueryEvent``, the
former ``AlternateHeaderLineEvent`` and ``ManipulateCellDataEvent``) were removed. Use the three
PSR-14 events under :php:`\Calien\Xlsexport\Event\Export` instead — see :ref:`developer`.

..  _migration-rewrite:

Rewriting an export
==================

Take the ``export`` query of a version 4 definition and express its parts as configuration: the
``FROM`` table becomes ``table``, the selected columns become ``select``, and each
``WHERE`` clause becomes a :ref:`condition <configuration-where>`. The ``%d`` placeholder that was
filled with the page id becomes ``###CURRENT_ID###``.

..  code-block:: typoscript
    :caption: Version 4

    mod.tx_xlsexport.settings.exports {
        addresses {
            label = Addresses
            table = tt_address
            export (
                select uid, first_name, last_name from tt_address
                where pid = %d and deleted = 0 and hidden = 0
            )
            exportfields {
                10 = uid
                20 = first_name
                30 = last_name
            }
            exportfieldnames {
                10 = ID
                20 = First name
                30 = Last name
            }
        }
    }

..  code-block:: typoscript
    :caption: Version 5

    mod.web_xlsexport {
        addresses {
            label = Addresses
            table = tt_address
            select {
                10 = uid
                20 = first_name
                30 = last_name
            }
            fieldLabels {
                10 = ID
                20 = First name
                30 = Last name
            }
            where {
                10 {
                    fieldName = pid
                    parameter = ###CURRENT_ID###
                    expressionType = eq
                    type = int
                }
            }
        }
    }

..  note::

    The ``deleted`` and ``hidden`` conditions from the version 4 SQL are no longer needed: the
    QueryBuilder applies TYPO3's default record restrictions automatically.
