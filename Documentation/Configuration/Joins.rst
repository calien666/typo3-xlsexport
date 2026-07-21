:navigation-title: Joining tables

..  include:: /Includes.rst.txt

..  _configuration-joins:

==============
Joining tables
==============

``join``, ``leftJoin`` and ``rightJoin`` are numeric lists of joins, mapped to
:php:`QueryBuilder::join()`, :php:`leftJoin()` and :php:`rightJoin()`. A join reads ``from`` an
existing table/alias, joins ``to`` another table (optionally under ``toAlias``), and its ``where``
list forms the ``ON`` condition using the same syntax as :ref:`configuration-where`:

..  code-block:: typoscript

    join {
        10 {
            from = tt_content
            to = pages
            toAlias = pages
            where {
                10 {
                    fieldName = tt_content.pid
                    parameter = pages.uid
                    expressionType = eq
                    # compare the two columns, no value binding
                    isColumn = 1
                }
            }
        }
    }

..  confval:: from
    :name: join-from
    :required: true
    :type: string

    The table or alias the join starts from.

..  confval:: to
    :name: join-to
    :required: true
    :type: string

    The table being joined in.

..  confval:: toAlias
    :name: join-to-alias
    :type: string

    Optional alias for the joined table. Defaults to ``to``.

..  confval:: where
    :name: join-where
    :type: list

    The join condition, using the condition syntax from :ref:`configuration-where`.
