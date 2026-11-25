<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\TestingFramework\Tests\Unit\Core\Functional\Framework\DataHandling\Snapshot;

use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Snapshot\DatabaseAccessor;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class DatabaseAccessorTest extends UnitTestCase
{
    public static function databaseValuesDataProvider(): iterable
    {
        yield 'encoded JSON' => [Types::JSON, '{"value":[1,true,null]}', Types::STRING];
        yield 'database datetime' => [Types::DATETIME_MUTABLE, '2026-11-14 12:34:56', Types::STRING];
        yield 'plain text' => [Types::TEXT, 'unchanged', Types::STRING];
        yield 'integer keeps its type' => [Types::INTEGER, '42', Types::INTEGER];
    }

    #[Test]
    #[DataProvider('databaseValuesDataProvider')]
    public function exportPreservesValuesAndNormalizesStringBoundTypes(string $type, string $value, string $expectedType): void
    {
        $table = new Table('tx_testingframework_snapshot');
        $table->addColumn('payload', $type);
        $schemaManager = self::createStub(AbstractSchemaManager::class);
        $schemaManager->method('listTables')->willReturn([$table]);
        $result = self::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([['payload' => $value]]);
        $queryBuilder = self::createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn(self::createStub(QueryRestrictionContainerInterface::class));
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);
        $connection = self::createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $export = (new DatabaseAccessor($connection))->export();
        self::assertSame(['payload' => $expectedType], $export[0]['columns']);
        self::assertSame([[$value]], $export[0]['items']);
    }
}
