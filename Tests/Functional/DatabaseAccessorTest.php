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

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Snapshot\DatabaseAccessor;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class DatabaseAccessorTest extends FunctionalTestCase
{
    #[Test]
    public function snapshotPreservesDatabaseValues(): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $schemaManager = $connection->createSchemaManager();
        $table = new Table('tx_testingframework_snapshot');
        $table->addColumn('uid', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('payload', Types::JSON, ['notnull' => false]);
        $table->addColumn('created_at', Types::DATETIME_MUTABLE);
        $table->addColumn('enabled', Types::BOOLEAN);
        $table->setPrimaryKey(['uid']);
        $schemaManager->createTable($table);

        try {
            $jsonValues = [
                '{"name":"Alice","nested":{"items":[1,true,null]}}',
                '[{"name":"Alice"},{"name":"Bob"}]',
                '"quoted \\"text\\""',
                '42',
                '1.5',
                'true',
                'false',
                'null',
                '{}',
                '[]',
                null,
            ];
            foreach ($jsonValues as $index => $json) {
                $connection->insert($table->getName(), [
                    'uid' => $index + 1,
                    'payload' => $json,
                    'created_at' => '2026-11-14 12:34:56',
                    'enabled' => $index % 2 === 0,
                ], [
                    'uid' => ParameterType::INTEGER,
                    'payload' => $json === null ? ParameterType::NULL : ParameterType::STRING,
                    'created_at' => ParameterType::STRING,
                    'enabled' => ParameterType::BOOLEAN,
                ]);
            }
            $expected = $connection->select(['*'], $table->getName(), [], [], ['uid' => 'ASC'])->fetchAllAssociative();
            self::assertCount(count($jsonValues), $expected);
            $subject = new DatabaseAccessor($connection);
            $snapshot = json_decode(json_encode($subject->export(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $connection->executeStatement('DELETE FROM ' . $connection->quoteIdentifier($table->getName()));

            $subject->import($snapshot);

            self::assertSame($expected, $connection->select(['*'], $table->getName(), [], [], ['uid' => 'ASC'])->fetchAllAssociative());
        } finally {
            $schemaManager->dropTable($table->getName());
        }
    }
}
