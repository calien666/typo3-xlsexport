<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class DummyTest extends UnitTestCase
{
    #[Test]
    public function dummy(): void
    {
        $this->assertTrue(true);
    }
}
