<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Tests\Unit\Service;

use Calien\Xlsexport\Service\ExportFilterService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ExportFilterServiceTest extends UnitTestCase
{
    /**
     * @param array<array-key, mixed> $expected
     */
    #[Test]
    #[DataProvider('sanitizeDataProvider')]
    public function sanitizeKeepsOnlyStringsAndNestedArrays(mixed $requestValue, array $expected): void
    {
        $subject = new ExportFilterService();

        $this->assertSame($expected, $subject->sanitize($requestValue));
    }

    public static function sanitizeDataProvider(): \Generator
    {
        yield 'missing parameter' => [
            'requestValue' => null,
            'expected' => [],
        ];
        yield 'scalar instead of an array' => [
            'requestValue' => 'location',
            'expected' => [],
        ];
        yield 'flat values' => [
            'requestValue' => [
                'location' => '12',
                'fromDate' => '2026-01-01',
            ],
            'expected' => [
                'location' => '12',
                'fromDate' => '2026-01-01',
            ],
        ];
        yield 'nested list' => [
            'requestValue' => [
                'categories' => ['3', '5'],
            ],
            'expected' => [
                'categories' => ['3', '5'],
            ],
        ];
        yield 'numbers become strings, objects and booleans are dropped' => [
            'requestValue' => [
                'page' => 4,
                'object' => new \stdClass(),
                'flag' => true,
            ],
            'expected' => [
                'page' => '4',
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $filter
     * @param list<array{name: string, value: string}> $expected
     */
    #[Test]
    #[DataProvider('formFieldsDataProvider')]
    public function formFieldsFlattenTheFilterIntoBracketNames(array $filter, array $expected): void
    {
        $subject = new ExportFilterService();

        $this->assertSame($expected, $subject->formFields($filter));
    }

    public static function formFieldsDataProvider(): \Generator
    {
        yield 'empty filter' => [
            'filter' => [],
            'expected' => [],
        ];
        yield 'flat values' => [
            'filter' => [
                'location' => '12',
            ],
            'expected' => [
                [
                    'name' => 'filter[location]',
                    'value' => '12',
                ],
            ],
        ];
        yield 'nested list keeps its indexes' => [
            'filter' => [
                'categories' => ['3', '5'],
            ],
            'expected' => [
                [
                    'name' => 'filter[categories][0]',
                    'value' => '3',
                ],
                [
                    'name' => 'filter[categories][1]',
                    'value' => '5',
                ],
            ],
        ];
    }
}
