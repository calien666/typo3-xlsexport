<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Export\Event;

use Psr\EventDispatcher\StoppableEventInterface;

final class AlternateExportQueryEvent implements StoppableEventInterface
{
    protected string $exportKey = '';

    /**
     * @var array <string, mixed>
     */
    protected array $exportConfiguration = [];

    /**
     * @param array $settings
     * @param string $config
     */
    public function __construct(array $settings, string $config)
    {
        $this->exportKey = $config;
        $this->exportConfiguration = $settings;
    }

    /**
     * checkExportConfigExists
     *
     * Event listener should call this method to check if access is needed
     *
     * @param string $exportKey
     * @return bool
     */
    public function checkExportConfigExists(string $exportKey): bool
    {
        return $exportKey === $this->exportKey;
    }

    public function alternateExportQuery(string $export): void
    {
        if ($this->exportConfiguration['export'] && !$this->exportConfiguration['manipulated']) {
            $this->exportConfiguration['export'] = $export;
            $this->exportConfiguration['manipulated'] = true;
        }
    }

    public function isPropagationStopped(): bool
    {
        $allManipulated = true;
        if (!array_key_exists('manipulated', $this->exportConfiguration) || !$this->exportConfiguration['manipulated']) {
            $allManipulated = false;
        }
        return $allManipulated;
    }

    public function getManipulatedSettings(): array
    {
        return $this->exportConfiguration;
    }
}
