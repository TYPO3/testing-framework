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

namespace TYPO3\TestingFramework\Tests\Functional;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Snapshot\DatabaseAccessor;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class DatabaseAccessorSequenceTest extends FunctionalTestCase
{
    public static function autoIncrementColumnsDataProvider(): iterable
    {
        yield 'uid' => ['uid', 'uid'];
        yield 'custom column' => ['record_id', 'record_id'];
        yield 'quoted mixed-case column' => ['entryId', '"entryId"'];
    }

    #[Test]
    #[DataProvider('autoIncrementColumnsDataProvider')]
    public function snapshotRestoresNextGeneratedId(string $columnName, string $schemaColumnName): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $schemaManager = $connection->createSchemaManager();
        $table = new Table('tx_testingframework_sequence');
        $table->addColumn($schemaColumnName, Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('title', Types::STRING, ['length' => 255]);
        $table->setPrimaryKey([$schemaColumnName]);
        $schemaManager->createTable($table);

        try {
            $connection->insert($table->getName(), [$columnName => 7, 'title' => 'first']);
            $connection->insert($table->getName(), [$columnName => 11, 'title' => 'last']);
            $subject = new DatabaseAccessor($connection);
            $snapshot = json_decode(json_encode($subject->export(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

            for ($iteration = 0; $iteration < 2; ++$iteration) {
                $connection->truncate($table->getName());
                $subject->import($snapshot);
                $connection->insert($table->getName(), ['title' => 'generated']);

                self::assertSame(12, (int)$connection->select([$columnName], $table->getName(), ['title' => 'generated'])->fetchOne());
                self::assertSame(3, $connection->count('*', $table->getName(), []));
            }
        } finally {
            $schemaManager->dropTable($table->getName());
        }
    }

    #[Test]
    public function snapshotRestoresTableWithoutAutoIncrementColumn(): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $schemaManager = $connection->createSchemaManager();
        $table = new Table('tx_testingframework_no_sequence');
        $table->addColumn('identifier', Types::STRING, ['length' => 255]);
        $table->addColumn('title', Types::STRING, ['length' => 255]);
        $table->setPrimaryKey(['identifier']);
        $schemaManager->createTable($table);

        try {
            $connection->insert($table->getName(), ['identifier' => 'example', 'title' => 'preserved']);
            $subject = new DatabaseAccessor($connection);
            $snapshot = json_decode(json_encode($subject->export(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $connection->truncate($table->getName());

            $subject->import($snapshot);

            self::assertSame('preserved', $connection->select(['title'], $table->getName(), ['identifier' => 'example'])->fetchOne());
        } finally {
            $schemaManager->dropTable($table->getName());
        }
    }
}
