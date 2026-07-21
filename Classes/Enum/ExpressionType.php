<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Enum;

/**
 * The comparison operators a `where`/`join` configuration may reference by keyword in TSconfig
 * (e.g. `expressionType = eq`). Values match the TYPO3 QueryBuilder ExpressionBuilder methods.
 */
enum ExpressionType: string
{
    case Equals = 'eq';
    case NotEquals = 'neq';
    case GreaterThan = 'gt';
    case GreaterThanOrEquals = 'gte';
    case LessThan = 'lt';
    case LessThanOrEquals = 'lte';
    case IsNull = 'isNull';
    case IsNotNull = 'isNotNull';
    case In = 'in';
    case InSet = 'inSet';
}
