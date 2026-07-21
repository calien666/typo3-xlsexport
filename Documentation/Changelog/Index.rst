:navigation-title: Changelog

..  include:: /Includes.rst.txt

..  _changelog:

=========
Changelog
=========

..  contents:: Table of contents
    :local:

..  _changelog-5-0-0:

5.0.0 - Declarative, QueryBuilder-based exports
===============================================

A rewrite that introduces a declarative export format and modernises the extension for TYPO3 13
and 14.

Breaking
--------

See :ref:`migration` for the full list and how to convert an export.

*   Exports are now declared as :ref:`structured page TSconfig <configuration>` and translated into a
    TYPO3 QueryBuilder query, replacing the previous ``check``/``list``/``export`` SQL statements.
*   The TSconfig namespace changed from ``mod.tx_xlsexport.settings.exports`` to
    ``mod.web_xlsexport``.
*   TYPO3 12 support was dropped; the extension now requires TYPO3 13.4 or 14.3 and PHP 8.2 to 8.5.
*   The Extbase controller, the ``SC_OPTIONS`` hooks and the previous events were removed.

Features
--------

*   Improved query building in TSconfig: read any table with filters, joins and literal select
    expressions.
*   The record count in the module is derived automatically from the export definition, instead of a
    separate query.
*   Configurable file name and export type (``xlsx``, ``xls``, ``ods``, ``csv``) per download.
*   The example export ships as a site set.
*   Three PSR-14 events (:ref:`developer`) to shape the generated spreadsheet.
*   A misconfigured export is skipped with a warning instead of breaking the whole module overview.
