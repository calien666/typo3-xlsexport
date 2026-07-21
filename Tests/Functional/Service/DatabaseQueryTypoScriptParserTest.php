<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Tests\Functional\Service;

use Calien\Xlsexport\Exception\ExpressionTypeNotValidException;
use Calien\Xlsexport\Exception\ParameterHasWrongTypeException;
use Calien\Xlsexport\Exception\TypeIsNotAllowedAsQuoteException;
use Calien\Xlsexport\Service\DatabaseQueryTypoScriptParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class DatabaseQueryTypoScriptParserTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['calien/xlsexport'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
    }

    /**
     * @param string[] $expectedHeaders
     */
    #[Test]
    #[DataProvider('comparisonExpressionProvider')]
    public function comparisonExpressionReturnsExpectedRows(string $expressionType, string $parameter, array $expectedHeaders): void
    {
        $subject = $this->get(DatabaseQueryTypoScriptParser::class);
        $query = $subject->buildQueryBuilderFromArray([
            'table' => 'tt_content',
            'select' => ['10' => 'header'],
            'where' => [
                '10' => [
                    'fieldName' => 'sorting',
                    'parameter' => $parameter,
                    'expressionType' => $expressionType,
                    'type' => 'int',
                ],
            ],
        ]);

        $headers = array_column($query->executeQuery()->fetchAllAssociative(), 'header');
        sort($headers);
        $this->assertSame($expectedHeaders, $headers);
    }

    public static function comparisonExpressionProvider(): \Generator
    {
        yield 'equals' => ['expressionType' => 'eq', 'parameter' => '20', 'expectedHeaders' => ['Second']];
        yield 'not equals' => ['expressionType' => 'neq', 'parameter' => '20', 'expectedHeaders' => ['First', 'Third']];
        yield 'greater than' => ['expressionType' => 'gt', 'parameter' => '20', 'expectedHeaders' => ['Third']];
        yield 'greater than or equals' => ['expressionType' => 'gte', 'parameter' => '20', 'expectedHeaders' => ['Second', 'Third']];
        yield 'less than' => ['expressionType' => 'lt', 'parameter' => '20', 'expectedHeaders' => ['First']];
        yield 'less than or equals' => ['expressionType' => 'lte', 'parameter' => '20', 'expectedHeaders' => ['First', 'Second']];
    }

    #[Test]
    public function currentIdPlaceholderIsReplacedWithGivenPageId(): void
    {
        $subject = $this->get(DatabaseQueryTypoScriptParser::class);
        $query = $subject->buildQueryBuilderFromArray([
            'table' => 'tt_content',
            'select' => ['10' => 'header'],
            'where' => [
                '10' => [
                    'fieldName' => 'pid',
                    'parameter' => '###CURRENT_ID###',
                    'expressionType' => 'eq',
                    'type' => 'int',
                ],
            ],
        ]);
        $subject->replacePlaceholderWithCurrentId($query, 2);

        $headers = array_column($query->executeQuery()->fetchAllAssociative(), 'header');
        $this->assertSame(['Third'], $headers);
    }

    #[Test]
    public function inWithIntegerArrayReturnsMatchingRows(): void
    {
        $subject = $this->get(DatabaseQueryTypoScriptParser::class);
        $query = $subject->buildQueryBuilderFromArray([
            'table' => 'tt_content',
            'select' => ['10' => 'header'],
            'where' => [
                '10' => [
                    'fieldName' => 'uid',
                    'parameter' => ['10' => '1', '20' => '3'],
                    'expressionType' => 'in',
                    'type' => 'int_array',
                ],
            ],
        ]);

        $headers = array_column($query->executeQuery()->fetchAllAssociative(), 'header');
        sort($headers);
        $this->assertSame(['First', 'Third'], $headers);
    }

    #[Test]
    public function inSetReturnsRowsContainingTheValue(): void
    {
        $subject = $this->get(DatabaseQueryTypoScriptParser::class);
        $query = $subject->buildQueryBuilderFromArray([
            'table' => 'tt_content',
            'select' => ['10' => 'header'],
            'where' => [
                '10' => [
                    'fieldName' => 'bodytext',
                    'parameter' => 'green',
                    'expressionType' => 'inSet',
                    'type' => 'string',
                ],
            ],
        ]);

        $headers = array_column($query->executeQuery()->fetchAllAssociative(), 'header');
        $this->assertSame(['First'], $headers);
    }

    #[Test]
    public function joinExposesColumnsFromTheJoinedTable(): void
    {
        $subject = $this->get(DatabaseQueryTypoScriptParser::class);
        $query = $subject->buildQueryBuilderFromArray([
            'table' => 'tt_content',
            'select' => ['10' => 'header', '20' => 'pages.title'],
            'where' => [
                '10' => [
                    'fieldName' => 'tt_content.uid',
                    'parameter' => '3',
                    'expressionType' => 'eq',
                    'type' => 'int',
                ],
            ],
            'join' => [
                '10' => [
                    'from' => 'tt_content',
                    'to' => 'pages',
                    'toAlias' => 'pages',
                    'where' => [
                        '10' => [
                            'fieldName' => 'tt_content.pid',
                            'parameter' => 'pages.uid',
                            'expressionType' => 'eq',
                            'isColumn' => '1',
                        ],
                    ],
                ],
            ],
        ]);

        $rows = $query->executeQuery()->fetchAllAssociative();
        $this->assertCount(1, $rows);
        $this->assertSame('Third', $rows[0]['header']);
        $this->assertSame('Sub', $rows[0]['title']);
    }

    #[Test]
    public function buildCountQueryReturnsNumberOfMatchingRows(): void
    {
        $subject = $this->get(DatabaseQueryTypoScriptParser::class);
        $query = $subject->buildCountQueryFromArray([
            'table' => 'tt_content',
            'select' => ['10' => 'uid'],
            'count' => '*',
            'where' => [
                '10' => [
                    'fieldName' => 'pid',
                    'parameter' => '1',
                    'expressionType' => 'eq',
                    'type' => 'int',
                ],
            ],
        ]);

        $this->assertSame(2, (int)$query->executeQuery()->fetchOne());
    }

    #[Test]
    public function configurationWithoutWhereReturnsAllRows(): void
    {
        $subject = $this->get(DatabaseQueryTypoScriptParser::class);
        $query = $subject->buildQueryBuilderFromArray([
            'table' => 'tt_content',
            'select' => ['10' => 'header'],
        ]);

        $this->assertCount(3, $query->executeQuery()->fetchAllAssociative());
    }

    #[Test]
    public function isNullBuildsAnIsNullPredicate(): void
    {
        $subject = $this->get(DatabaseQueryTypoScriptParser::class);
        $query = $subject->buildQueryBuilderFromArray([
            'table' => 'tt_content',
            'select' => ['10' => 'uid'],
            'where' => [
                '10' => [
                    'fieldName' => 'bodytext',
                    'parameter' => '',
                    'expressionType' => 'isNull',
                    'type' => 'string',
                ],
            ],
        ]);

        $this->assertStringContainsString('IS NULL', $query->getSQL());
    }

    #[Test]
    public function isNotNullBuildsAnIsNotNullPredicate(): void
    {
        $subject = $this->get(DatabaseQueryTypoScriptParser::class);
        $query = $subject->buildQueryBuilderFromArray([
            'table' => 'tt_content',
            'select' => ['10' => 'uid'],
            'where' => [
                '10' => [
                    'fieldName' => 'bodytext',
                    'parameter' => '',
                    'expressionType' => 'isNotNull',
                    'type' => 'string',
                ],
            ],
        ]);

        $this->assertStringContainsString('IS NOT NULL', $query->getSQL());
    }

    #[Test]
    public function unknownExpressionTypeThrowsException(): void
    {
        $subject = $this->get(DatabaseQueryTypoScriptParser::class);

        $this->expectException(ExpressionTypeNotValidException::class);
        $this->expectExceptionCode(1731081406988);

        $subject->buildQueryBuilderFromArray([
            'table' => 'tt_content',
            'select' => ['10' => 'uid'],
            'where' => [
                '10' => [
                    'fieldName' => 'pid',
                    'parameter' => '1',
                    'expressionType' => 'notAnOperator',
                    'type' => 'int',
                ],
            ],
        ]);
    }

    #[Test]
    public function unknownParameterTypeThrowsException(): void
    {
        $subject = $this->get(DatabaseQueryTypoScriptParser::class);

        $this->expectException(TypeIsNotAllowedAsQuoteException::class);
        $this->expectExceptionCode(1731082482678);

        $subject->buildQueryBuilderFromArray([
            'table' => 'tt_content',
            'select' => ['10' => 'uid'],
            'where' => [
                '10' => [
                    'fieldName' => 'pid',
                    'parameter' => '1',
                    'expressionType' => 'eq',
                    'type' => 'notAType',
                ],
            ],
        ]);
    }

    #[Test]
    public function isColumnWithNonStringParameterThrowsException(): void
    {
        $subject = $this->get(DatabaseQueryTypoScriptParser::class);

        $this->expectException(ParameterHasWrongTypeException::class);
        $this->expectExceptionCode(1731093539911);

        $subject->buildQueryBuilderFromArray([
            'table' => 'tt_content',
            'select' => ['10' => 'uid'],
            'where' => [
                '10' => [
                    'fieldName' => 'pid',
                    'parameter' => ['10' => 'pages.uid'],
                    'expressionType' => 'eq',
                    'isColumn' => '1',
                    'type' => 'int',
                ],
            ],
        ]);
    }
}
