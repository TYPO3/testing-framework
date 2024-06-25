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

namespace TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Snapshot;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;

/**
 * Implement the database snapshot and callback logic.
 * This is helpful when tests need expensive setUp() to prime the database
 * with rows: Subsequent tests can re-use the rows from first test to skip
 * the expensive calculation.
 *
 * @internal Use FunctionalTestCase->withDatabaseSnapshot() to leverage this.
 */
class DatabaseSnapshot
{
    /**
     * Data up to 10 MiB is kept in memory
     */
    private const VALUE_IN_MEMORY_THRESHOLD = 1024 ** 2 * 10;

    private static DatabaseSnapshot $instance;
    private array $inMemoryImport = [];

    public static function initialize(string $sqliteDir, string $identifier): void
    {
        self::$instance = new self($sqliteDir, $identifier);
    }

    public static function instance(): self
    {
        return self::$instance;
    }

    private function __construct(
        private readonly string $sqliteDir,
        private readonly string $identifier
    ) {}

    /**
     * Create a new snapshot. This is called for the *first* test in a test case.
     */
    public function create(DatabaseAccessor $accessor, Connection $connection): void
    {
        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            // With sqlite, we simply copy the db-file to a different place
            $connection->close();
            copy(
                $this->sqliteDir . 'test_' . $this->identifier . '.sqlite',
                $this->sqliteDir . 'test_' . $this->identifier . '.snapshot.sqlite'
            );
            $this->inMemoryImport = [true];
        } else {
            // With non-sqlite, we fetch rows from all tables and park the content in memory
            $export = $accessor->export();
            $serialized = json_encode($export);
            // It's not the exact consumption due to serialization literals... fine
            if (strlen($serialized) <= self::VALUE_IN_MEMORY_THRESHOLD) {
                $this->inMemoryImport = $export;
            } else {
                throw new \RuntimeException('Export data set too large. Reduce data set or do not use snapshot.', 1630203176);
            }
        }
    }

    /**
     * Restore a snapshot. This is called for subsequent tests in a test case.
     */
    public function restore(DatabaseAccessor $accessor, Connection $connection): void
    {
        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $connection->close();
            copy(
                $this->sqliteDir . 'test_' . $this->identifier . '.snapshot.sqlite',
                $this->sqliteDir . 'test_' . $this->identifier . '.sqlite'
            );
        } else {
            $accessor->import($this->inMemoryImport);
        }
    }
}
