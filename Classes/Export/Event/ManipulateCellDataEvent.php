<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Export\Event;

final class ManipulateCellDataEvent
{
    private string $columnName;

    /**
     * @var array<array-key, mixed> $currentRow
     */
    private array $currentRow;

    private mixed $value;

    public function __construct(
        string $columnName,
        array $currentRow,
        mixed $value
    ) {
        $this->columnName = $columnName;
        $this->currentRow = $currentRow;
        $this->value = $value;
    }

    public function getColumnName(): string
    {
        return $this->columnName;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getCurrentRow(): array
    {
        return $this->currentRow;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}
