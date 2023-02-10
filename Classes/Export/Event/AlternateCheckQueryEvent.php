<?php

/**
 * Markus Hofmann
 * 12.10.21 22:05
 * churchevent
 */

declare(strict_types=1);

namespace Calien\Xlsexport\Export\Event;

use Psr\EventDispatcher\StoppableEventInterface;

final class AlternateCheckQueryEvent implements StoppableEventInterface
{
    /**
     * @var array|string[]
     */
    protected array $exportKeys = [];
    /**
     * @var array
     */
    protected array $exportConfiguration = [];

    /**
     * @param array $settings
     */
    public function __construct(array $settings)
    {
        foreach ($settings as $exportConfigKey => $exportConfig) {
            $keyWithoutDot = str_replace('.', '', $exportConfigKey);
            $this->exportKeys[] = $keyWithoutDot;
            $this->exportConfiguration[$exportConfigKey] = $exportConfig;
        }
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
        return in_array($exportKey, $this->exportKeys);
    }

    public function alternateCheckQuery(string $exportKey, string $check)
    {
        $exportConfig = sprintf('%s.', $exportKey);
        if ($this->exportConfiguration[$exportConfig]['check'] && !$this->exportConfiguration[$exportConfig]['manipulated']) {
            $this->exportConfiguration[$exportConfig]['check'] = $check;
            $this->exportConfiguration[$exportConfig]['manipulated'] = true;
        }
    }

    public function isPropagationStopped(): bool
    {
        $allManipulated = true;
        foreach ($this->exportConfiguration as $config) {
            if (!array_key_exists('manipulated', $config) || !$config['manipulated']) {
                $allManipulated = false;
            }
        }
        return $allManipulated;
    }

    public function getManipulatedSettings(): array
    {
        return $this->exportConfiguration;
    }
}
