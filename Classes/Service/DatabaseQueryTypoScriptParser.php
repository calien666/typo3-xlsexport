<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Service;

use Calien\Xlsexport\Exception\ExpressionTypeNotValidException;
use Calien\Xlsexport\Exception\ParameterHasWrongTypeException;
use Calien\Xlsexport\Exception\TypeIsNotAllowedAsQuoteException;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This Service parses the TSconfig array to a valid QueryBuilder
 */
final class DatabaseQueryTypoScriptParser
{
    /**
     * @param array{
     *     table: non-empty-string,
     *     alias?: non-empty-string,
     *     select: non-empty-string[],
     *     count?: non-empty-string,
     *     selectLiteral?: non-empty-string[],
     *     where: array<array-key, array{
     *      fieldName: string,
     *      parameter: float|int|string|float[]|int[]|string[],
     *      type: Connection::PARAM_*,
     *      expressionType: string,
     *      isColumn?: bool
     *   }>,
     *     join?: array<array{
     *      from: non-empty-string,
     *      to: non-empty-string,
     *     toAlias?: non-empty-string,
     *      where: array<array{
     *       fieldName: string,
     *       parameter: float|int|string|float[]|int[]|string[],
     *       type: Connection::PARAM_*,
     *       expressionType: string,
     *       isColumn?: bool
     *    }>
     *  }>,
     *     leftJoin?: array<array{
     *      from: non-empty-string,
     *      to: non-empty-string,
     *     toAlias?: non-empty-string,
     *      where: array<array{
     *       fieldName: string,
     *       parameter: float|int|string|float[]|int[]|string[],
     *       type: Connection::PARAM_*,
     *       expressionType: string,
     *       isColumn?: bool
     *    }>
     *  }>,
     *     rightJoin?: array<array{
     *      from: non-empty-string,
     *      to: non-empty-string,
     *     toAlias?: non-empty-string,
     *      where: array<array{
     *       fieldName: string,
     *       parameter: float|int|string|float[]|int[]|string[],
     *       type: Connection::PARAM_*,
     *       expressionType: string,
     *       isColumn?: bool
     *    }>
     *  }>,
     * } $configuration
     * @throws ExpressionTypeNotValidException
     * @throws TypeIsNotAllowedAsQuoteException
     */
    public function buildQueryBuilderFromArray(array $configuration): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($configuration['table']);
        $statement = $queryBuilder
            ->select(...array_values($configuration['select']))
            ->from($configuration['table'], $configuration['alias'] ?? null);

        if (($configuration['selectLiteral'] ?? []) !== []) {
            $statement->selectLiteral(...array_values($configuration['selectLiteral']));
        }
        if ($configuration['where'] !== []) {
            $where = [];
            foreach ($configuration['where'] as $whereConfiguration) {
                $where[] = $this->buildForExpressionType($queryBuilder, $whereConfiguration);
            }

            $statement->where(...array_values($where));
        }

        if (($configuration['join'] ?? []) !== []) {
            foreach ($configuration['join'] as $equiJoin) {
                $this->buildEquiJoin($statement, $queryBuilder, $equiJoin);
            }
        }

        if (($configuration['leftJoin'] ?? []) !== []) {
            foreach ($configuration['leftJoin'] as $leftJoin) {
                $this->buildLeftJoin($statement, $queryBuilder, $leftJoin);
            }
        }

        if (($configuration['rightJoin'] ?? []) !== []) {
            foreach ($configuration['rightJoin'] as $rightJoin) {
                $this->buildRightJoin($statement, $queryBuilder, $rightJoin);
            }
        }

        return $statement;
    }

    /**
     * @param array{
     *     table: non-empty-string,
     *     alias?: non-empty-string,
     *     select: non-empty-string[],
     *     count?: non-empty-string,
     *     selectLiteral?: non-empty-string[],
     *     where: array<array-key, array{
     *      fieldName: string,
     *      parameter: float|int|string|float[]|int[]|string[],
     *      type: Connection::PARAM_*,
     *      expressionType: string,
     *      isColumn?: bool
     *   }>,
     *     join?: array<array{
     *      from: non-empty-string,
     *      to: non-empty-string,
     *     toAlias?: non-empty-string,
     *      where: array<array{
     *       fieldName: string,
     *       parameter: float|int|string|float[]|int[]|string[],
     *       type: Connection::PARAM_*,
     *       expressionType: string,
     *       isColumn?: bool
     *    }>
     *  }>,
     *     leftJoin?: array<array{
     *      from: non-empty-string,
     *      to: non-empty-string,
     *     toAlias?: non-empty-string,
     *      where: array<array{
     *       fieldName: string,
     *       parameter: float|int|string|float[]|int[]|string[],
     *       type: Connection::PARAM_*,
     *       expressionType: string,
     *       isColumn?: bool
     *    }>
     *  }>,
     *     rightJoin?: array<array{
     *      from: non-empty-string,
     *      to: non-empty-string,
     *     toAlias?: non-empty-string,
     *      where: array<array{
     *       fieldName: string,
     *       parameter: float|int|string|float[]|int[]|string[],
     *       type: Connection::PARAM_*,
     *       expressionType: string,
     *       isColumn?: bool
     *    }>
     *  }>,
     * } $configuration
     * @throws ExpressionTypeNotValidException
     * @throws TypeIsNotAllowedAsQuoteException
     */
    public function buildCountQueryFromArray(array $configuration): QueryBuilder
    {
        $statement = $this->buildQueryBuilderFromArray($configuration);
        $statement->getConcreteQueryBuilder()->resetOrderBy();
        $statement->count($configuration['count'] ?? '*');

        return $statement;
    }

    public function replacePlaceholderWithCurrentId(QueryBuilder $statement, int $currentId): void
    {
        foreach ($statement->getParameters() as $key => $param) {
            if ($param === '###CURRENT_ID###') {
                $statement->setParameter($key, $currentId, Connection::PARAM_INT);
            }
        }
    }

    /**
     * @param array{
     *      fieldName: string,
     *      parameter: float|int|string|float[]|int[]|string[],
     *      type: Connection::PARAM_*,
     *      expressionType: string,
     *      isColumn?: bool
     *   } $configuration
     * @throws ExpressionTypeNotValidException
     * @throws TypeIsNotAllowedAsQuoteException
     */
    private function buildForExpressionType(
        QueryBuilder $queryBuilder,
        array $configuration
    ): string {
        return match ($configuration['expressionType']) {
            'eq' => $this->buildEquals($queryBuilder, $configuration),
            'neq' => $this->buildNotEquals($queryBuilder, $configuration),
            'gt' => $this->buildGreaterThan($queryBuilder, $configuration),
            'gte' => $this->buildGreaterThanOrEquals($queryBuilder, $configuration),
            'lt' => $this->buildLessThan($queryBuilder, $configuration),
            'lte' => $this->buildLessThanOrEquals($queryBuilder, $configuration),
            'isNull' => $this->buildIsNull($queryBuilder, $configuration['fieldName']),
            'isNotNull' => $this->buildIsNotNull($queryBuilder, $configuration['fieldName']),
            'in' => $this->buildIn($queryBuilder, $configuration['fieldName'], $configuration['parameter'], $configuration['type']),
            'inSet' => $this->buildInSet($queryBuilder, $configuration['fieldName'], $configuration['parameter']),
            default => throw new ExpressionTypeNotValidException(
                sprintf('The given expression type "%s" is not valid', $configuration['expressionType']),
                1731081406988
            )
        };
    }

    /**
     * @param array{
     *      fieldName: string,
     *      parameter: float|int|string|float[]|int[]|string[],
     *      type: Connection::PARAM_*,
     *      expressionType: string,
     *      isColumn?: bool
     *   } $configuration
     * @throws ParameterHasWrongTypeException
     * @throws \ReflectionException
     */
    private function buildEquals(QueryBuilder $queryBuilder, array $configuration): string
    {
        return $queryBuilder->expr()->eq(
            $configuration['fieldName'],
            $this->generateValue($queryBuilder, $configuration)
        );
    }

    /**
     * @param array{
     *      fieldName: string,
     *      parameter: float|int|string|float[]|int[]|string[],
     *      type: Connection::PARAM_*,
     *      expressionType: string,
     *      isColumn?: bool
     *   } $configuration
     * @throws ParameterHasWrongTypeException
     * @throws \ReflectionException
     */
    private function buildNotEquals(QueryBuilder $queryBuilder, array $configuration): string
    {
        return $queryBuilder->expr()->neq(
            $configuration['fieldName'],
            $this->generateValue($queryBuilder, $configuration)
        );
    }

    /**
     * @param array{
     *      fieldName: string,
     *      parameter: float|int|string|float[]|int[]|string[],
     *      type: Connection::PARAM_*,
     *      expressionType: string,
     *      isColumn?: bool
     *   } $configuration
     * @throws ParameterHasWrongTypeException
     * @throws \ReflectionException
     */
    private function buildGreaterThan(QueryBuilder $queryBuilder, array $configuration): string
    {
        return $queryBuilder->expr()->gt(
            $configuration['fieldName'],
            $this->generateValue($queryBuilder, $configuration)
        );
    }

    /**
     * @param array{
     *      fieldName: string,
     *      parameter: float|int|string|float[]|int[]|string[],
     *      type: Connection::PARAM_*,
     *      expressionType: string,
     *      isColumn?: bool
     *   } $configuration
     * @throws ParameterHasWrongTypeException
     * @throws \ReflectionException
     */
    private function buildGreaterThanOrEquals(QueryBuilder $queryBuilder, array $configuration): string
    {
        return $queryBuilder->expr()->gte(
            $configuration['fieldName'],
            $this->generateValue($queryBuilder, $configuration)
        );
    }

    /**
     * @param array{
     *      fieldName: string,
     *      parameter: float|int|string|float[]|int[]|string[],
     *      type: Connection::PARAM_*,
     *      expressionType: string,
     *      isColumn?: bool
     *   } $configuration
     * @throws ParameterHasWrongTypeException
     * @throws \ReflectionException
     */
    private function buildLessThan(QueryBuilder $queryBuilder, array $configuration): string
    {
        return $queryBuilder->expr()->lt(
            $configuration['fieldName'],
            $this->generateValue($queryBuilder, $configuration)
        );
    }

    /**
     * @param array{
     *      fieldName: string,
     *      parameter: float|int|string|float[]|int[]|string[],
     *      type: Connection::PARAM_*,
     *      expressionType: string,
     *      isColumn?: bool
     *   } $configuration
     * @throws ParameterHasWrongTypeException
     * @throws \ReflectionException
     */
    private function buildLessThanOrEquals(QueryBuilder $queryBuilder, array $configuration): string
    {
        return $queryBuilder->expr()->lte(
            $configuration['fieldName'],
            $this->generateValue($queryBuilder, $configuration)
        );
    }

    private function buildIsNull(QueryBuilder $queryBuilder, string $fieldName): string
    {
        return $queryBuilder->expr()->isNull($fieldName);
    }

    private function buildIsNotNull(QueryBuilder $queryBuilder, string $fieldName): string
    {
        return $queryBuilder->expr()->isNotNull($fieldName);
    }

    /**
     * @param array<float|int|string>|float|int|string $parameter
     */
    private function buildInSet(QueryBuilder $queryBuilder, string $fieldName, mixed $parameter): string
    {
        return $queryBuilder->expr()->inSet($fieldName, $queryBuilder->createNamedParameter($parameter, Connection::PARAM_STR));
    }

    /**
     * @param array<float|int|string>|float|int|string $parameter
     * @param string|int|ParameterType $type Connection::PARAM_*
     * @throws TypeIsNotAllowedAsQuoteException
     * @throws ParameterHasWrongTypeException
     * @see Connection::PARAM_*
     */
    private function buildIn(QueryBuilder $queryBuilder, string $fieldName, mixed $parameter, string|int|ParameterType $type): string
    {
        if (!is_array($parameter)) {
            throw new ParameterHasWrongTypeException(
                sprintf('Parameter has to be array for building "in" statement, "%s" given', gettype($parameter)),
                1731094230854
            );
        }
        $quotedParameter = match ($type) {
            Connection::PARAM_STR => $queryBuilder->quoteArrayBasedValueListToStringList($parameter),
            Connection::PARAM_INT => $queryBuilder->quoteArrayBasedValueListToIntegerList($parameter),
            default => throw new TypeIsNotAllowedAsQuoteException(
                'The type can not be quoted for usage as `in`',
                1731082482677
            )
        };
        return $queryBuilder->expr()->in($fieldName, $quotedParameter);
    }

    /**
     * @param array{
     *      from: non-empty-string,
     *      to: non-empty-string,
     *     toAlias?: non-empty-string,
     *      where: array<array{
     *       fieldName: string,
     *       parameter: float|int|string|float[]|int[]|string[],
     *       type: Connection::PARAM_*,
     *       expressionType: string,
     *       isColumn?: bool
     *    }>
     *  } $joinConfiguration
     * @throws ExpressionTypeNotValidException
     * @throws TypeIsNotAllowedAsQuoteException
     */
    private function buildEquiJoin(QueryBuilder $statement, QueryBuilder $queryBuilder, array $joinConfiguration): void
    {
        $where = [];
        foreach ($joinConfiguration['where'] as $joinWhere) {
            $where[] = $this->buildForExpressionType($queryBuilder, $joinWhere);
        }
        $statement->join(
            $joinConfiguration['from'],
            $joinConfiguration['to'],
            $joinConfiguration['toAlias'] ?? $joinConfiguration['to'],
            ($where !== []) ? (string)$queryBuilder->expr()->and(...array_values($where)) : null
        );
    }

    /**
     * @param array{
     *      from: non-empty-string,
     *      to: non-empty-string,
     *     toAlias?: non-empty-string,
     *      where: array<array{
     *       fieldName: string,
     *       parameter: float|int|string|float[]|int[]|string[],
     *       type: Connection::PARAM_*,
     *       expressionType: string,
     *       isColumn?: bool
     *    }>
     *  } $joinConfiguration
     * @throws ExpressionTypeNotValidException
     * @throws TypeIsNotAllowedAsQuoteException
     */
    private function buildLeftJoin(QueryBuilder $statement, QueryBuilder $queryBuilder, array $joinConfiguration): void
    {
        $where = [];
        foreach ($joinConfiguration['where'] as $joinWhere) {
            $where[] = $this->buildForExpressionType($queryBuilder, $joinWhere);
        }
        $statement->leftJoin(
            $joinConfiguration['from'],
            $joinConfiguration['to'],
            $joinConfiguration['toAlias'] ?? $joinConfiguration['to'],
            ($where !== []) ? (string)$queryBuilder->expr()->and(...array_values($where)) : null
        );
    }

    /**
     * @param array{
     *      from: non-empty-string,
     *      to: non-empty-string,
     *     toAlias?: non-empty-string,
     *      where: array<array{
     *       fieldName: string,
     *       parameter: float|int|string|float[]|int[]|string[],
     *       type: Connection::PARAM_*,
     *       expressionType: string,
     *       isColumn?: bool
     *    }>
     *  } $joinConfiguration
     * @throws ExpressionTypeNotValidException
     * @throws TypeIsNotAllowedAsQuoteException
     */
    private function buildRightJoin(QueryBuilder $statement, QueryBuilder $queryBuilder, array $joinConfiguration): void
    {
        $where = [];
        foreach ($joinConfiguration['where'] as $joinWhere) {
            $where[] = $this->buildForExpressionType($queryBuilder, $joinWhere);
        }
        $statement->rightJoin(
            $joinConfiguration['from'],
            $joinConfiguration['to'],
            $joinConfiguration['toAlias'] ?? $joinConfiguration['to'],
            ($where !== []) ? (string)$queryBuilder->expr()->and(...array_values($where)) : null
        );
    }

    /**
     * @param array{
     *        fieldName: string,
     *        parameter: float|int|string|float[]|int[]|string[],
     *        type: class-string|string,
     *        expressionType: string,
     *        isColumn?: bool
     *     } $configuration
     * @throws ParameterHasWrongTypeException
     * @throws \ReflectionException
     */
    private function generateValue(QueryBuilder $queryBuilder, array $configuration): string
    {
        // true not possible because of TypoScript load
        if (($configuration['isColumn'] ?? '0') === '1') {
            if (!is_string($configuration['parameter'])) {
                throw new ParameterHasWrongTypeException(
                    sprintf('Parameter has to be string, if "isColumn" is set to true, "%s" given', gettype($configuration['parameter'])),
                    1731093539911
                );
            }
            $value = $queryBuilder->quoteIdentifier($configuration['parameter']);
        } else {
            if ($configuration['type'] instanceof ParameterType) {
                $constant = $configuration['type'];
            } else {
                $partsOfType = GeneralUtility::trimExplode('::', $configuration['type']);
                $class = $partsOfType[0];
                if ($partsOfType[0] === 'Connection') {
                    $class = Connection::class;
                }
                $reflection = new \ReflectionClass($class);
                $constant = $reflection->getConstant($partsOfType[1]);
            }

            $value = $queryBuilder->createNamedParameter($configuration['parameter'], $constant);
        }

        return $value;
    }
}
