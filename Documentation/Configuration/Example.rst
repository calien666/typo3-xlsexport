:navigation-title: Complete example

..  include:: /Includes.rst.txt

..  _configuration-example:

================
Complete example
================

The following export lists the content elements of the current page together with the title of
their page:

..  code-block:: typoscript
    :caption: EXT:my_extension/Configuration/page.tsconfig

    mod.web_xlsexport {
        content {
            label = Page content elements
            table = tt_content
            format = xlsx
            select {
                10 = tt_content.uid
                20 = tt_content.header
                30 = tt_content.CType
                40 = pages.title
            }
            fieldLabels {
                10 = ID
                20 = Header
                30 = Type
                40 = Page
            }
            count = *
            where {
                10 {
                    fieldName = tt_content.pid
                    parameter = ###CURRENT_ID###
                    expressionType = eq
                    type = int
                }
            }
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
                            isColumn = 1
                        }
                    }
                }
            }
        }
    }
