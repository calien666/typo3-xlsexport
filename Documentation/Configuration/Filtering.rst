:navigation-title: Filtering rows

..  include:: /Includes.rst.txt

..  _configuration-where:

==============
Filtering rows
==============

..  contents:: Table of contents
    :local:

``where`` is a numeric list of conditions that are combined with ``AND`` and passed to
:php:`QueryBuilder::where()`. Each condition maps to one method of the
:ref:`ExpressionBuilder <t3coreapi:database-query-builder>`:

..  code-block:: typoscript

    where {
        10 {
            fieldName = pid
            parameter = ###CURRENT_ID###
            expressionType = eq
            type = int
        }
    }

..  confval:: fieldName
    :name: where-field-name
    :required: true
    :type: string

    The column the condition applies to.

..  confval:: expressionType
    :name: where-expression-type
    :required: true
    :type: string

    The comparison operator, see :ref:`configuration-expression-types`.

..  confval:: parameter
    :name: where-parameter
    :type: string or list

    The value to compare against. A scalar for most operators, or a list for ``in``. The special
    value ``###CURRENT_ID###`` is replaced with the uid of the page selected in the module.

..  confval:: type
    :name: where-type
    :type: string

    The parameter type keyword, see :ref:`configuration-parameter-types`. Not needed for
    ``isNull``/``isNotNull`` or when ``isColumn`` is set.

..  confval:: isColumn
    :name: where-is-column
    :type: boolean

    Set to ``1`` to treat ``parameter`` as a column identifier (quoted with
    :php:`quoteIdentifier()`) instead of a value. Use this to compare two columns, typically in a
    join condition.

..  _configuration-expression-types:

Expression types
================

..  list-table::
    :header-rows: 1

    *   -   ``expressionType``
        -   ExpressionBuilder method
        -   Meaning
    *   -   ``eq``
        -   :php:`eq()`
        -   equal to
    *   -   ``neq``
        -   :php:`neq()`
        -   not equal to
    *   -   ``lt``
        -   :php:`lt()`
        -   less than
    *   -   ``lte``
        -   :php:`lte()`
        -   less than or equal to
    *   -   ``gt``
        -   :php:`gt()`
        -   greater than
    *   -   ``gte``
        -   :php:`gte()`
        -   greater than or equal to
    *   -   ``in``
        -   :php:`in()`
        -   value in a list (use an ``int_array``/``string_array`` ``type``)
    *   -   ``inSet``
        -   :php:`inSet()`
        -   value contained in a comma-separated column
    *   -   ``isNull``
        -   :php:`isNull()`
        -   column is ``NULL``
    *   -   ``isNotNull``
        -   :php:`isNotNull()`
        -   column is not ``NULL``

..  _configuration-parameter-types:

Parameter types
===============

The ``type`` keyword selects the :php:`\TYPO3\CMS\Core\Database\Connection` parameter type
used to bind the value:

..  list-table::
    :header-rows: 1

    *   -   ``type``
        -   Connection constant
    *   -   ``int``
        -   :php:`Connection::PARAM_INT`
    *   -   ``string``
        -   :php:`Connection::PARAM_STR`
    *   -   ``bool``
        -   :php:`Connection::PARAM_BOOL`
    *   -   ``null``
        -   :php:`Connection::PARAM_NULL`
    *   -   ``lob``
        -   :php:`Connection::PARAM_LOB`
    *   -   ``int_array``
        -   :php:`Connection::PARAM_INT_ARRAY` (for ``in`` with integers)
    *   -   ``string_array``
        -   :php:`Connection::PARAM_STR_ARRAY` (for ``in`` with strings)
