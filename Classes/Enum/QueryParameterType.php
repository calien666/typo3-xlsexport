<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Enum;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\Connection;

/**
 * The parameter types an export `where`/`join`/`in` configuration may reference by keyword in
 * TSconfig (e.g. `type = int`). Each case owns its mapping to a TYPO3 QueryBuilder parameter type.
 */
enum QueryParameterType: string
{
    case Integer = 'int';
    case String = 'string';
    case Boolean = 'bool';
    case Null = 'null';
    case Lob = 'lob';
    case IntegerArray = 'int_array';
    case StringArray = 'string_array';

    public function toParameterType(): ParameterType|ArrayParameterType
    {
        return match ($this) {
            self::Integer => Connection::PARAM_INT,
            self::String => Connection::PARAM_STR,
            self::Boolean => Connection::PARAM_BOOL,
            self::Null => Connection::PARAM_NULL,
            self::Lob => Connection::PARAM_LOB,
            self::IntegerArray => Connection::PARAM_INT_ARRAY,
            self::StringArray => Connection::PARAM_STR_ARRAY,
        };
    }
}
