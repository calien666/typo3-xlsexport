<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Event;

final class ManipulateRowEntryEvent
{
    /**
     * @param array<array-key, mixed> $row
     * @param string[] $fieldLabels
     */
    public function __construct(
        private array $row,
        private readonly array $fieldLabels,
        private readonly string $configurationKey
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function getRow(): array
    {
        return $this->row;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    public function setRow(array $row): void
    {
        $this->row = $row;
    }

    /**
     * @return string[]
     */
    public function getFieldLabels(): array
    {
        return $this->fieldLabels;
    }

    public function getConfigurationKey(): string
    {
        return $this->configurationKey;
    }
}
