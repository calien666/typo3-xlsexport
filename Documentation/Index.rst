..  include:: /Includes.rst.txt

..  _start:

=====================
Calien - XLS Exporter
=====================

:Extension key:
    xlsexport

:Package name:
    calien/xlsexport

:Version:
    |release|

:Language:
    en

:Author:
    Markus Hofmann, Frank Berger

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

:Rendered:
    |today|

----

A backend module that lets editors download preconfigured database query results as spreadsheet
files (``xlsx``, ``xls``, ``ods`` or ``csv``). Exports are described declaratively in page TSconfig
and translated into a TYPO3 :ref:`QueryBuilder <t3coreapi:database-query-builder>` query.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What the extension does and why the export format changed in version 5.

    ..  card:: :ref:`Installation <installation>`

        Install via Composer and activate the site set.

    ..  card:: :ref:`For editors <editors>`

        Download an export from the backend module.

    ..  card:: :ref:`Configuration <configuration>`

        The TSconfig export format for integrators, mapped to the QueryBuilder.

    ..  card:: :ref:`Developer <developer>`

        The PSR-14 events that let you shape the generated spreadsheet.

    ..  card:: :ref:`Migration <migration>`

        Breaking changes and how to move an export from version 4 to version 5.

    ..  card:: :ref:`Changelog <changelog>`

        Notable changes to this extension.

**Table of contents:**

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Editors/Index
    Configuration/Index
    Developer/Index
    Migration/Index
    Changelog/Index
