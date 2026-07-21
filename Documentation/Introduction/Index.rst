:navigation-title: Introduction

..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

..  contents:: Table of contents
    :local:

What it does
============

The XLS Exporter adds a backend module (:guilabel:`Web > XLS Exporter`) that offers editors one or
more preconfigured *exports* for the currently selected page. Each export runs a database query and
streams the result to the browser as a spreadsheet file. The editor picks a file name and one of the
supported formats — ``xlsx``, ``xls``, ``ods`` or ``csv`` — and downloads the file.

Exports are defined by integrators in page TSconfig. A single definition describes which table to
read, which columns to select, how to filter and join, and which column headers to write — see
:ref:`configuration`.

..  _introduction-declarative:

Declarative exports
===================

Version 5 introduces a declarative export format: a definition describes *what* to read — table,
columns, filters and joins — and the extension builds the query for it with the TYPO3
:ref:`QueryBuilder <t3coreapi:database-query-builder>`. Integrators no longer write SQL statements
by hand, which makes exports easier to read, maintain and extend. See :ref:`migration` for how to
convert an export from version 4.

Features
========

*   Read any table, with ``SELECT``, ``WHERE``, ``JOIN`` / ``LEFT JOIN`` / ``RIGHT JOIN`` and a
    record count, all expressed as TSconfig.
*   Output as ``xlsx``, ``xls``, ``ods`` or ``csv`` via
    `PhpSpreadsheet <https://phpspreadsheet.readthedocs.io/>`__.
*   Delivered as a site set, so integrators enable it per site.
*   Three PSR-14 events to shape the generated spreadsheet — see :ref:`developer`.
