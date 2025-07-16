<?php

declare(strict_types=1);

namespace Calien\Xlsexport\Tests\Functional\Service;

use Calien\Xlsexport\Exception\ExpressionTypeNotValidException;
use Calien\Xlsexport\Exception\TypeIsNotAllowedAsQuoteException;
use Calien\Xlsexport\Service\DatabaseQueryTypoScriptParser;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class DatabaseQueryTypoScriptParserTest extends FunctionalTestCase
{
    #[Test]
    public function simpleQueryArrayIsParsedCorrect(): void
    {
        $tsConfig = [
            'table' => 'pages',
            'select' => [
                'uid',
                'pid',
                'title',
            ],
            'where' => [
                [
                    'fieldName' => 'pid',
                    'parameter' => 1,
                    'type' => 'Connection::PARAM_INT',
                    'expressionType' => 'eq',
                ],
            ],
        ];

        $subject = new DatabaseQueryTypoScriptParser();

        $statement = $subject->buildQueryBuilderFromArray($tsConfig);

        $this->assertInstanceOf(QueryBuilder::class, $statement);

        $connectionParams = $statement->getConnection()->getParams();
        $escapeCharacter = match ($connectionParams['driver'] ?? '') {
            'pdo_pgsql', 'pdo_sqlite' => '"',
            default => '`'
        };

        if ((new Typo3Version())->getMajorVersion() <= 12) {
            $parts = $statement->getQueryParts();

            $this->assertIsArray($parts['select']);
            $this->assertContains(sprintf('%1$suid%1$s', $escapeCharacter), $parts['select']);
            $this->assertContains(sprintf('%1$spid%1$s', $escapeCharacter), $parts['select']);
            $this->assertContains(sprintf('%1$stitle%1$s', $escapeCharacter), $parts['select']);
            $this->assertInstanceOf(CompositeExpression::class, $parts['where']);
            $this->assertEquals('AND', $parts['where']->getType());
            $this->assertEquals(1, $parts['where']->count());
            $this->assertEquals(sprintf('%1$spid%1$s = :dcValue1', $escapeCharacter), (string)$parts['where']);
            $this->assertIsArray($parts['from']);
            $from = array_pop($parts['from']);
            $this->assertIsArray($from);
            $this->assertEquals(['alias' => null, 'table' => sprintf('%1$spages%1$s', $escapeCharacter)], $from);
        } else {
            $this->assertIsArray($statement->getSelect());
            $this->assertContains('"uid"', $statement->getSelect());
            $this->assertContains('"pid"', $statement->getSelect());
            $this->assertContains('"title"', $statement->getSelect());
            $this->assertEquals('"pid" = :dcValue1', (string)$statement->getWhere());
            $this->assertIsArray($statement->getFrom());
            $fromArray = $statement->getFrom();
            $from = array_pop($fromArray);
            $this->assertEquals('"pages"', $from->table);
            $this->assertNull($from->alias);
        }
    }

    #[Test]
    public function invalidExpressionTypeThrowsException(): void
    {
        $tsConfig = [
            'table' => 'pages',
            'select' => [
                'uid',
                'pid',
                'title',
            ],
            'where' => [
                [
                    'fieldName' => 'pid',
                    'parameter' => 1,
                    'type' => 'Connection::PARAM_INT',
                    'expressionType' => 'not-allowed-expression-type',
                ],
            ],
        ];

        $subject = new DatabaseQueryTypoScriptParser();

        $this->expectException(ExpressionTypeNotValidException::class);
        $subject->buildQueryBuilderFromArray($tsConfig);
    }

    #[Test]
    public function invalidFieldTypeThrowsException(): void
    {
        $tsConfig = [
            'table' => 'pages',
            'select' => [
                'uid',
                'pid',
                'title',
            ],
            'where' => [
                [
                    'fieldName' => 'pid',
                    'parameter' => [1],
                    'type' => 'Connection::PARAM_BOOL',
                    'expressionType' => 'in',
                ],
            ],
        ];

        $subject = new DatabaseQueryTypoScriptParser();

        $this->expectException(TypeIsNotAllowedAsQuoteException::class);
        $subject->buildQueryBuilderFromArray($tsConfig);
    }

    #[Test]
    public function equiJoinBuildWorksCorrect(): void
    {
        $tsConfig = [
            'table' => 'pages',
            'select' => [
                'uid',
                'pid',
                'title',
            ],
            'where' => [
                [
                    'fieldName' => 'pid',
                    'parameter' => 1,
                    'type' => 'Connection::PARAM_INT',
                    'expressionType' => 'eq',
                ],
            ],
            'join' => [
                [
                    'from' => 'pages',
                    'to' => 'tt_content',
                    'where' => [
                        [
                            'fieldName' => 'pages.uid',
                            'parameter' => 'tt_content.pid',
                            'type' => 'Connection::PARAM_STR',
                            'expressionType' => 'eq',
                            'isColumn' => true,
                        ],
                    ],
                ],
            ],
        ];

        $subject = new DatabaseQueryTypoScriptParser();

        $statement = $subject->buildQueryBuilderFromArray($tsConfig);

        $this->assertInstanceOf(QueryBuilder::class, $statement);

        //$parts = $statement->getQueryParts();
    }
}
