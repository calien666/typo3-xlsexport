<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Service;

use Calien\Xlsexport\Enum\ExpressionType;
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
 *     parameter: float|int|string|array<array-key, float|int|string>,
 *     type?: non-empty-string,
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
 *     select: array<array-key, non-empty-string>,
 *     count?: non-empty-string,
 *     selectLiteral?: array<array-key, non-empty-string>,
 *     where?: array<array-key, WhereConfiguration>,
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
        $whereConfigurations = $configuration['where'] ?? [];
        if ($whereConfigurations !== []) {
            $where = [];
            foreach ($whereConfigurations as $whereConfiguration) {
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
    private function buildForExpressionType(QueryBuilder $queryBuilder, array $configuration): string
    {
        $expressionType = ExpressionType::tryFrom($configuration['expressionType']);
        if ($expressionType === null) {
            throw new ExpressionTypeNotValidException(
                sprintf('The given expression type "%s" is not valid', $configuration['expressionType']),
                1731081406988
            );
        }

        $expr = $queryBuilder->expr();

        return match ($expressionType) {
            ExpressionType::Equals => $expr->eq($configuration['fieldName'], $this->generateValue($queryBuilder, $configuration)),
            ExpressionType::NotEquals => $expr->neq($configuration['fieldName'], $this->generateValue($queryBuilder, $configuration)),
            ExpressionType::GreaterThan => $expr->gt($configuration['fieldName'], $this->generateValue($queryBuilder, $configuration)),
            ExpressionType::GreaterThanOrEquals => $expr->gte($configuration['fieldName'], $this->generateValue($queryBuilder, $configuration)),
            ExpressionType::LessThan => $expr->lt($configuration['fieldName'], $this->generateValue($queryBuilder, $configuration)),
            ExpressionType::LessThanOrEquals => $expr->lte($configuration['fieldName'], $this->generateValue($queryBuilder, $configuration)),
            ExpressionType::IsNull => $expr->isNull($configuration['fieldName']),
            ExpressionType::IsNotNull => $expr->isNotNull($configuration['fieldName']),
            ExpressionType::In => $expr->in($configuration['fieldName'], $queryBuilder->createNamedParameter($configuration['parameter'], $this->resolveParameterType($configuration['type'] ?? ''))),
            ExpressionType::InSet => $this->buildInSet($queryBuilder, $configuration['fieldName'], $configuration['parameter']),
        };
    }

    /**
     * @param float|int|string|float[]|int[]|string[] $parameter
     * @throws ParameterHasWrongTypeException
     */
    private function buildInSet(QueryBuilder $queryBuilder, string $fieldName, mixed $parameter): string
    {
        if (!is_scalar($parameter)) {
            throw new ParameterHasWrongTypeException(
                sprintf('Parameter for "inSet" has to be scalar, "%s" given', gettype($parameter)),
                1784678400
            );
        }

        // SQLite's inSet emulation rejects placeholders, so the value is quoted inline on every platform.
        return $queryBuilder->expr()->inSet($fieldName, $queryBuilder->quote((string)$parameter));
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
            $this->resolveParameterType($configuration['type'] ?? '')
        );
    }

    /**
     * Resolves a TSconfig type keyword such as "int" to its QueryBuilder parameter-type constant.
     *
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
