<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Service;

use Calien\Xlsexport\Enum\QueryParameterType;
use Calien\Xlsexport\Exception\ExpressionTypeNotValidException;
use Calien\Xlsexport\Exception\ParameterHasWrongTypeException;
use Calien\Xlsexport\Exception\TypeIsNotAllowedAsQuoteException;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Translates a plain TSconfig export configuration into a TYPO3 core QueryBuilder.
 *
 * The parser is the security boundary of the extension: editors configure exports through the
 * declarative TSconfig format handled here, never through raw SQL, so every value flows through
 * {@see QueryBuilder::createNamedParameter()} or {@see QueryBuilder::quoteIdentifier()}.
 *
 * Values arrive from TSconfig as strings, including the parameter `type` keyword (e.g. "int" or
 * "string") and the `isColumn` flag ("0"/"1"); {@see resolveParameterType()} maps the keyword to a
 * TYPO3 QueryBuilder parameter type via {@see QueryParameterType}.
 *
 * @phpstan-type WhereConfiguration array{
 *     fieldName: string,
 *     parameter: float|int|string|float[]|int[]|string[],
 *     type: non-empty-string,
 *     expressionType: string,
 *     isColumn?: non-empty-string
 * }
 * @phpstan-type JoinConfiguration array{
 *     from: non-empty-string,
 *     to: non-empty-string,
 *     toAlias?: non-empty-string,
 *     where: array<array-key, WhereConfiguration>
 * }
 * @phpstan-type QueryConfiguration array{
 *     table: non-empty-string,
 *     alias?: non-empty-string,
 *     select: array<non-empty-string, non-empty-string>,
 *     count?: non-empty-string,
 *     selectLiteral?: array<non-empty-string, non-empty-string>,
 *     where: array<array-key, WhereConfiguration>,
 *     join?: array<array-key, JoinConfiguration>,
 *     leftJoin?: array<array-key, JoinConfiguration>,
 *     rightJoin?: array<array-key, JoinConfiguration>
 * }
 */
final class DatabaseQueryTypoScriptParser
{
    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    /**
     * @param QueryConfiguration $configuration
     * @throws ExpressionTypeNotValidException
     * @throws TypeIsNotAllowedAsQuoteException
     * @throws ParameterHasWrongTypeException
     */
    public function buildQueryBuilderFromArray(array $configuration): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($configuration['table']);
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

            $statement->where(...$where);
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
     * @param QueryConfiguration $configuration
     * @throws ExpressionTypeNotValidException
     * @throws TypeIsNotAllowedAsQuoteException
     * @throws ParameterHasWrongTypeException
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
     * @param WhereConfiguration $configuration
     * @throws ExpressionTypeNotValidException
     * @throws TypeIsNotAllowedAsQuoteException
     * @throws ParameterHasWrongTypeException
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
     * @param WhereConfiguration $configuration
     * @throws ParameterHasWrongTypeException
     * @throws TypeIsNotAllowedAsQuoteException
     */
    private function buildEquals(QueryBuilder $queryBuilder, array $configuration): string
    {
        return $queryBuilder->expr()->eq(
            $configuration['fieldName'],
            $this->generateValue($queryBuilder, $configuration)
        );
    }

    /**
     * @param WhereConfiguration $configuration
     * @throws ParameterHasWrongTypeException
     * @throws TypeIsNotAllowedAsQuoteException
     */
    private function buildNotEquals(QueryBuilder $queryBuilder, array $configuration): string
    {
        return $queryBuilder->expr()->neq(
            $configuration['fieldName'],
            $this->generateValue($queryBuilder, $configuration)
        );
    }

    /**
     * @param WhereConfiguration $configuration
     * @throws ParameterHasWrongTypeException
     * @throws TypeIsNotAllowedAsQuoteException
     */
    private function buildGreaterThan(QueryBuilder $queryBuilder, array $configuration): string
    {
        return $queryBuilder->expr()->gt(
            $configuration['fieldName'],
            $this->generateValue($queryBuilder, $configuration)
        );
    }

    /**
     * @param WhereConfiguration $configuration
     * @throws ParameterHasWrongTypeException
     * @throws TypeIsNotAllowedAsQuoteException
     */
    private function buildGreaterThanOrEquals(QueryBuilder $queryBuilder, array $configuration): string
    {
        return $queryBuilder->expr()->gte(
            $configuration['fieldName'],
            $this->generateValue($queryBuilder, $configuration)
        );
    }

    /**
     * @param WhereConfiguration $configuration
     * @throws ParameterHasWrongTypeException
     * @throws TypeIsNotAllowedAsQuoteException
     */
    private function buildLessThan(QueryBuilder $queryBuilder, array $configuration): string
    {
        return $queryBuilder->expr()->lt(
            $configuration['fieldName'],
            $this->generateValue($queryBuilder, $configuration)
        );
    }

    /**
     * @param WhereConfiguration $configuration
     * @throws ParameterHasWrongTypeException
     * @throws TypeIsNotAllowedAsQuoteException
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
     * @param non-empty-string $type TSconfig type string, e.g. "Connection::PARAM_INT"
     * @throws TypeIsNotAllowedAsQuoteException
     * @throws ParameterHasWrongTypeException
     */
    private function buildIn(QueryBuilder $queryBuilder, string $fieldName, mixed $parameter, string $type): string
    {
        if (!is_array($parameter)) {
            throw new ParameterHasWrongTypeException(
                sprintf('Parameter has to be array for building "in" statement, "%s" given', gettype($parameter)),
                1731094230854
            );
        }
        $quotedParameter = match ($this->resolveParameterType($type)) {
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
     * @param JoinConfiguration $joinConfiguration
     * @throws ExpressionTypeNotValidException
     * @throws TypeIsNotAllowedAsQuoteException
     * @throws ParameterHasWrongTypeException
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
            ($where !== []) ? (string)$queryBuilder->expr()->and(...$where) : null
        );
    }

    /**
     * @param JoinConfiguration $joinConfiguration
     * @throws ExpressionTypeNotValidException
     * @throws TypeIsNotAllowedAsQuoteException
     * @throws ParameterHasWrongTypeException
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
            ($where !== []) ? (string)$queryBuilder->expr()->and(...$where) : null
        );
    }

    /**
     * @param JoinConfiguration $joinConfiguration
     * @throws ExpressionTypeNotValidException
     * @throws TypeIsNotAllowedAsQuoteException
     * @throws ParameterHasWrongTypeException
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
            ($where !== []) ? (string)$queryBuilder->expr()->and(...$where) : null
        );
    }

    /**
     * @param WhereConfiguration $configuration
     * @throws ParameterHasWrongTypeException
     * @throws TypeIsNotAllowedAsQuoteException
     */
    private function generateValue(QueryBuilder $queryBuilder, array $configuration): string
    {
        // "true" is impossible here: TypoScript delivers the flag as the string "1".
        if (($configuration['isColumn'] ?? '0') === '1') {
            if (!is_string($configuration['parameter'])) {
                throw new ParameterHasWrongTypeException(
                    sprintf('Parameter has to be string, if "isColumn" is set to true, "%s" given', gettype($configuration['parameter'])),
                    1731093539911
                );
            }
            return $queryBuilder->quoteIdentifier($configuration['parameter']);
        }

        return $queryBuilder->createNamedParameter(
            $configuration['parameter'],
            $this->resolveParameterType($configuration['type'])
        );
    }

    /**
     * Resolves a TSconfig type string such as "Connection::PARAM_INT" to its parameter-type constant.
     *
     * @param non-empty-string $type
     * @throws TypeIsNotAllowedAsQuoteException
     */
    private function resolveParameterType(string $type): ParameterType|ArrayParameterType
    {
        $keyword = QueryParameterType::tryFrom(strtolower(trim($type)));
        if ($keyword === null) {
            throw new TypeIsNotAllowedAsQuoteException(
                sprintf(
                    'The parameter type "%s" is not supported, use one of: %s',
                    $type,
                    implode(', ', array_column(QueryParameterType::cases(), 'value'))
                ),
                1731082482678
            );
        }

        return $keyword->toParameterType();
    }
}
