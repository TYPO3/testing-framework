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

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DefaultRestrictionContainer;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Snapshot\DatabaseAccessor;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class DatabaseAccessorTest extends UnitTestCase
{
    public static function databaseValuesDataProvider(): iterable
    {
        yield 'encoded JSON' => [Types::JSON, '{"value":[1,true,null]}'];
        yield 'database datetime' => [Types::DATETIME_MUTABLE, '2026-11-14 12:34:56'];
        yield 'plain text' => [Types::TEXT, 'unchanged'];
    }

    #[Test]
    #[DataProvider('databaseValuesDataProvider')]
    public function exportedStringsCanBeRestoredWithoutConversion(string $type, string $value): void
    {
        $table = new Table('tx_testingframework_snapshot');
        $table->addColumn('payload', $type);
        $schemaManager = self::createStub(AbstractSchemaManager::class);
        $schemaManager->method('listTables')->willReturn([$table]);
        $result = self::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([['payload' => $value]]);
        $queryBuilder = self::createStub(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn(self::createStub(DefaultRestrictionContainer::class));
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);
        $connection = self::createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $export = (new DatabaseAccessor($connection))->export();
        $restoredValue = Type::getType($export[0]['columns']['payload'])->convertToDatabaseValue(
            $export[0]['items'][0][0],
            new PostgreSQLPlatform()
        );

        self::assertSame($value, $restoredValue);
    }
}
