<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Service;

use Calien\Xlsexport\Exception\InvalidExportConfigurationException;

/**
 * Validates a raw TSconfig export section into the shape the parser consumes, so malformed editor
 * configuration fails with a clear exception instead of a PHP error deep inside the parser. Value
 * validity (known operators and type keywords) stays the parser's concern; this only guards structure.
 *
 * @phpstan-import-type QueryConfiguration from DatabaseQueryTypoScriptParser
 * @phpstan-import-type WhereConfiguration from DatabaseQueryTypoScriptParser
 * @phpstan-import-type JoinConfiguration from DatabaseQueryTypoScriptParser
 */
final class ExportConfigurationValidator
{
    /**
     * @param array<int|string, mixed> $configuration
     * @return QueryConfiguration
     * @throws InvalidExportConfigurationException
     */
    public function validate(array $configuration): array
    {
        $validated = [
            'table' => $this->asNonEmptyString($configuration['table'] ?? null, 'table'),
            'select' => $this->validateFieldList($configuration['select'] ?? null, 'select'),
        ];

        if (($alias = $configuration['alias'] ?? null) !== null) {
            $validated['alias'] = $this->asNonEmptyString($alias, 'alias');
        }
        if (($count = $configuration['count'] ?? null) !== null) {
            $validated['count'] = $this->asNonEmptyString($count, 'count');
        }
        if (isset($configuration['selectLiteral'])) {
            $validated['selectLiteral'] = $this->validateFieldList($configuration['selectLiteral'], 'selectLiteral');
        }
        if (isset($configuration['where'])) {
            $validated['where'] = $this->validateWhereList($configuration['where'], 'where');
        }
        if (isset($configuration['join'])) {
            $validated['join'] = $this->validateJoinList($configuration['join'], 'join');
        }
        if (isset($configuration['leftJoin'])) {
            $validated['leftJoin'] = $this->validateJoinList($configuration['leftJoin'], 'leftJoin');
        }
        if (isset($configuration['rightJoin'])) {
            $validated['rightJoin'] = $this->validateJoinList($configuration['rightJoin'], 'rightJoin');
        }

        return $validated;
    }

    /**
     * @param array<int|string, mixed> $configuration
     * @return array{fieldLabels: array<array-key, non-empty-string>, format: non-empty-string}
     * @throws InvalidExportConfigurationException
     */
    public function validatePresentation(array $configuration): array
    {
        $format = $configuration['format'] ?? null;

        return [
            'fieldLabels' => $this->validateFieldList($configuration['fieldLabels'] ?? null, 'fieldLabels'),
            'format' => (is_string($format) && $format !== '') ? $format : 'xlsx',
        ];
    }

    /**
     * @return non-empty-string
     * @throws InvalidExportConfigurationException
     */
    private function asNonEmptyString(mixed $value, string $key): string
    {
        if (!is_string($value) || $value === '') {
            throw new InvalidExportConfigurationException(
                sprintf('The export configuration key "%s" must be a non-empty string.', $key),
                1784678410
            );
        }

        return $value;
    }

    /**
     * @return array<array-key, non-empty-string>
     * @throws InvalidExportConfigurationException
     */
    private function validateFieldList(mixed $fields, string $key): array
    {
        if (!is_array($fields) || $fields === []) {
            throw new InvalidExportConfigurationException(
                sprintf('The export configuration key "%s" must be a non-empty list of fields.', $key),
                1784678411
            );
        }

        $validated = [];
        foreach ($fields as $index => $field) {
            $validated[$index] = $this->asNonEmptyString($field, sprintf('%s.%s', $key, (string)$index));
        }

        return $validated;
    }

    /**
     * @return array<array-key, WhereConfiguration>
     * @throws InvalidExportConfigurationException
     */
    private function validateWhereList(mixed $conditions, string $key): array
    {
        if (!is_array($conditions)) {
            throw new InvalidExportConfigurationException(
                sprintf('The "%s" configuration must be a list of conditions.', $key),
                1784678412
            );
        }

        $validated = [];
        foreach ($conditions as $index => $condition) {
            $validated[$index] = $this->validateWhere($condition, sprintf('%s.%s', $key, (string)$index));
        }

        return $validated;
    }

    /**
     * @return WhereConfiguration
     * @throws InvalidExportConfigurationException
     */
    private function validateWhere(mixed $condition, string $key): array
    {
        if (!is_array($condition)) {
            throw new InvalidExportConfigurationException(
                sprintf('The condition "%s" must be an array.', $key),
                1784678413
            );
        }

        $validated = [
            'fieldName' => $this->asNonEmptyString($condition['fieldName'] ?? null, $key . '.fieldName'),
            'parameter' => $this->validateParameter($condition['parameter'] ?? null, $key . '.parameter'),
            'expressionType' => $this->asNonEmptyString($condition['expressionType'] ?? null, $key . '.expressionType'),
        ];
        if (($type = $condition['type'] ?? null) !== null) {
            $validated['type'] = $this->asNonEmptyString($type, $key . '.type');
        }
        if (($isColumn = $condition['isColumn'] ?? null) !== null) {
            $validated['isColumn'] = $this->asNonEmptyString($isColumn, $key . '.isColumn');
        }

        return $validated;
    }

    /**
     * @return float|int|string|array<array-key, float|int|string>
     * @throws InvalidExportConfigurationException
     */
    private function validateParameter(mixed $parameter, string $key): float|int|string|array
    {
        if (is_float($parameter) || is_int($parameter) || is_string($parameter)) {
            return $parameter;
        }
        if (!is_array($parameter)) {
            throw new InvalidExportConfigurationException(
                sprintf('The parameter "%s" must be a scalar or a list of scalars.', $key),
                1784678414
            );
        }

        $validated = [];
        foreach ($parameter as $index => $value) {
            if (!is_float($value) && !is_int($value) && !is_string($value)) {
                throw new InvalidExportConfigurationException(
                    sprintf('Every value of parameter "%s" must be a scalar.', $key),
                    1784678415
                );
            }
            $validated[$index] = $value;
        }

        return $validated;
    }

    /**
     * @return array<array-key, JoinConfiguration>
     * @throws InvalidExportConfigurationException
     */
    private function validateJoinList(mixed $joins, string $key): array
    {
        if (!is_array($joins)) {
            throw new InvalidExportConfigurationException(
                sprintf('The "%s" configuration must be a list of joins.', $key),
                1784678416
            );
        }

        $validated = [];
        foreach ($joins as $index => $join) {
            $validated[$index] = $this->validateJoin($join, sprintf('%s.%s', $key, (string)$index));
        }

        return $validated;
    }

    /**
     * @return JoinConfiguration
     * @throws InvalidExportConfigurationException
     */
    private function validateJoin(mixed $join, string $key): array
    {
        if (!is_array($join)) {
            throw new InvalidExportConfigurationException(
                sprintf('The join "%s" must be an array.', $key),
                1784678417
            );
        }

        $validated = [
            'from' => $this->asNonEmptyString($join['from'] ?? null, $key . '.from'),
            'to' => $this->asNonEmptyString($join['to'] ?? null, $key . '.to'),
            'where' => $this->validateWhereList($join['where'] ?? [], $key . '.where'),
        ];
        if (($toAlias = $join['toAlias'] ?? null) !== null) {
            $validated['toAlias'] = $this->asNonEmptyString($toAlias, $key . '.toAlias');
        }

        return $validated;
    }
}
