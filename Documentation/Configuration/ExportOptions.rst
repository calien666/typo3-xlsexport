:navigation-title: Export options

..  include:: /Includes.rst.txt

..  _configuration-options:

==============
Export options
==============

These are the keys of a single export definition below ``mod.web_xlsexport.<key>``.

..  confval-menu::
    :name: export-options
    :display: table
    :type:
    :Default:

    ..  confval:: table
        :name: option-table
        :required: true
        :type: string

        The database table to read from — the argument of
        :php:`QueryBuilder::from()`.

    ..  confval:: alias
        :name: option-alias
        :type: string

        Optional table alias, passed as the second argument of :php:`QueryBuilder::from()`. Use it
        when a join needs to reference this table under a short name.

    ..  confval:: select
        :name: option-select
        :required: true
        :type: list

        The columns to read, as a numeric TSconfig list (``10``, ``20``, …). Each value is one
        column passed to :php:`QueryBuilder::select()`. Prefix with the table or alias
        (``tt_content.uid``) when selecting across joins.

    ..  confval:: selectLiteral
        :name: option-select-literal
        :type: list

        Additional raw select expressions passed to :php:`QueryBuilder::selectLiteral()`, for
        example ``COUNT(*) AS amount``. Unlike ``select``, these are not quoted — only use
        integrator-controlled values here.

    ..  confval:: count
        :name: option-count
        :type: string
        :Default: '*'

        The column counted for the record number shown next to the export in the module, using
        :php:`QueryBuilder::count()`. ``*`` counts all rows.

    ..  confval:: fieldLabels
        :name: option-field-labels
        :required: true
        :type: list

        The column headers written into the first row of the spreadsheet, as a numeric list aligned
        by position with ``select``.

    ..  confval:: format
        :name: option-format
        :type: string
        :Default: xlsx

        The default spreadsheet format: ``xlsx``, ``xls``, ``ods`` or ``csv``. Editors can override
        it per download.

    ..  confval:: label
        :name: option-label
        :type: string

        The label shown for the export in the module. May be a plain string or an ``LLL:``
        reference. Defaults to the ``table`` name.

    ..  confval:: where
        :name: option-where
        :type: list

        The filter conditions — see :ref:`configuration-where`.

    ..  confval:: join
        :name: option-join
        :type: list

        Inner joins — see :ref:`configuration-joins`.

    ..  confval:: leftJoin
        :name: option-left-join
        :type: list

        Left joins — see :ref:`configuration-joins`.

    ..  confval:: rightJoin
        :name: option-right-join
        :type: list

        Right joins — see :ref:`configuration-joins`.
