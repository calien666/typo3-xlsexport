<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class Typo3VersionTest extends UnitTestCase
{
    #[Test]
    public function installedCoreMajorVersionIsSupported(): void
    {
        $this->assertContains(
            (new Typo3Version())->getMajorVersion(),
            [13, 14],
            'The installed TYPO3 core major version must be one the extension supports.'
        );
    }
}
