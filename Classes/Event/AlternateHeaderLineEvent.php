<?php

/**
 * Markus Hofmann
 * 12.10.21 23:17
 * churchevent
 */

declare(strict_types=1);

namespace Calien\Xlsexport\Event;

final class AlternateHeaderLineEvent
{
    /**
     * @param string[] $headerFieldLabels
     */
    public function __construct(
        private array $headerFieldLabels,
        private readonly string $configuration
    ) {}

    /**
     * @return string[]
     */
    public function getHeaderFieldLabels(): array
    {
        return $this->headerFieldLabels;
    }

    /**
     * @param string[] $headerFieldLabels
     */
    public function setHeaderFieldLabels(array $headerFieldLabels): void
    {
        $this->headerFieldLabels = $headerFieldLabels;
    }

    public function getConfiguration(): string
    {
        return $this->configuration;
    }
}
