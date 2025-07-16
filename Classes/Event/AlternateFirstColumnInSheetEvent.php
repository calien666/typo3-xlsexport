<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Event;

class AlternateFirstColumnInSheetEvent
{
    private string $firstColumn = 'A';

    public function getFirstColumn(): string
    {
        return $this->firstColumn;
    }

    public function setFirstColumn(string $firstColumn): void
    {
        $this->firstColumn = $firstColumn;
    }

}
