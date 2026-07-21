<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Tests\Functional\Service;

use Calien\Xlsexport\Exception\InvalidExportConfigurationException;
use Calien\Xlsexport\Service\ExportConfigurationValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ExportConfigurationValidatorTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['calien/xlsexport'];

    /**
     * @param array<int|string, mixed> $configuration
     */
    #[Test]
    #[DataProvider('invalidConfigurationProvider')]
    public function invalidConfigurationThrowsException(array $configuration, int $expectedCode): void
    {
        $subject = $this->get(ExportConfigurationValidator::class);

        $this->expectException(InvalidExportConfigurationException::class);
        $this->expectExceptionCode($expectedCode);

        $subject->validate($configuration);
    }

    /**
     * @return \Generator<string, array{configuration: array<int|string, mixed>, expectedCode: int}>
     */
    public static function invalidConfigurationProvider(): \Generator
    {
        yield 'missing table' => [
            'configuration' => ['select' => ['10' => 'uid']],
            'expectedCode' => 1784678410,
        ];
        yield 'missing select' => [
            'configuration' => ['table' => 'tt_content'],
            'expectedCode' => 1784678411,
        ];
        yield 'empty select' => [
            'configuration' => ['table' => 'tt_content', 'select' => []],
            'expectedCode' => 1784678411,
        ];
        yield 'where is not a list' => [
            'configuration' => ['table' => 'tt_content', 'select' => ['10' => 'uid'], 'where' => 'nope'],
            'expectedCode' => 1784678412,
        ];
        yield 'where entry is not an array' => [
            'configuration' => ['table' => 'tt_content', 'select' => ['10' => 'uid'], 'where' => ['10' => 'nope']],
            'expectedCode' => 1784678413,
        ];
        yield 'parameter list contains a non-scalar' => [
            'configuration' => [
                'table' => 'tt_content',
                'select' => ['10' => 'uid'],
                'where' => ['10' => ['fieldName' => 'uid', 'parameter' => ['10' => ['deep' => 'x']], 'expressionType' => 'in']],
            ],
            'expectedCode' => 1784678415,
        ];
        yield 'join is not a list' => [
            'configuration' => ['table' => 'tt_content', 'select' => ['10' => 'uid'], 'join' => 'nope'],
            'expectedCode' => 1784678416,
        ];
        yield 'join entry misses "from"' => [
            'configuration' => [
                'table' => 'tt_content',
                'select' => ['10' => 'uid'],
                'join' => ['10' => ['to' => 'pages']],
            ],
            'expectedCode' => 1784678410,
        ];
    }

    #[Test]
    public function validKeepsTheNormalizedStructure(): void
    {
        $subject = $this->get(ExportConfigurationValidator::class);
        $configuration = [
            'table' => 'tt_content',
            'select' => ['10' => 'uid', '20' => 'header'],
            'count' => '*',
            'where' => [
                '10' => ['fieldName' => 'pid', 'parameter' => '5', 'expressionType' => 'eq', 'type' => 'int'],
            ],
        ];

        $this->assertSame($configuration, $subject->validate($configuration));
    }

    #[Test]
    public function validatePresentationDefaultsFormatToXlsx(): void
    {
        $subject = $this->get(ExportConfigurationValidator::class);

        $this->assertSame(
            ['fieldLabels' => ['10' => 'ID', '20' => 'Header'], 'format' => 'xlsx'],
            $subject->validatePresentation(['fieldLabels' => ['10' => 'ID', '20' => 'Header']])
        );
    }
}
