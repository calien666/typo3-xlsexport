<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Service;

/**
 * Prepares the free-form filter values of the reserved `filter` request parameter.
 *
 * The module hands the sanitized filter to the export events and forwards it as hidden fields with every
 * download, so the filter applied to the overview is the one applied to the file.
 */
final class ExportFilterService
{
    /**
     * @return array<array-key, mixed> String values and nested arrays of string values
     */
    public function sanitize(mixed $requestValue): array
    {
        if (!is_array($requestValue)) {
            return [];
        }
        $filter = [];
        foreach ($requestValue as $key => $value) {
            if (is_array($value)) {
                $filter[$key] = $this->sanitize($value);
            } elseif (is_string($value) || is_int($value) || is_float($value)) {
                $filter[$key] = (string)$value;
            }
        }

        return $filter;
    }

    /**
     * @param array<array-key, mixed> $filter
     * @return list<array{name: string, value: string}>
     */
    public function formFields(array $filter, string $prefix = 'filter'): array
    {
        $fields = [];
        foreach ($filter as $key => $value) {
            $name = sprintf('%s[%s]', $prefix, $key);
            if (is_array($value)) {
                array_push($fields, ...$this->formFields($value, $name));
            } elseif (is_string($value)) {
                $fields[] = [
                    'name' => $name,
                    'value' => $value,
                ];
            }
        }

        return $fields;
    }
}
