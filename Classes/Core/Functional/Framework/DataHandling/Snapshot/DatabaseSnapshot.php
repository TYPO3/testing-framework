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

    /**
     * @param string $identifier Identifies the *instance* database file, which several
     *                           test case classes may share.
     * @param string|null $snapshotIdentifier Identifies the snapshot taken of it. Defaults
     *                           to $identifier for backwards compatibility, but callers
     *                           sharing one instance between test case classes must pass a
     *                           per test case value, otherwise one test case class restores
     *                           the snapshot another one created.
     */
    public static function initialize(string $sqliteDir, string $identifier, ?string $snapshotIdentifier = null): void
    {
        self::$instance = new self($sqliteDir, $identifier, $snapshotIdentifier ?? $identifier);
    }

    public static function instance(): self
    {
        return self::$instance;
    }

    private function __construct(
        private readonly string $sqliteDir,
        private readonly string $identifier,
        private readonly string $snapshotIdentifier
    ) {}

    /**
     * Create a new snapshot. This is called for the *first* test of a test case class.
     */
    public function create(DatabaseAccessor $accessor, Connection $connection): void
    {
        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            // With sqlite, we simply copy the db-file to a different place
            $connection->close();
            copy(
                $this->sqliteDir . 'test_' . $this->identifier . '.sqlite',
                $this->sqliteDir . 'test_' . $this->snapshotIdentifier . '.snapshot.sqlite'
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
     * Restore a snapshot. This is called for subsequent tests of a test case class.
     */
    public function restore(DatabaseAccessor $accessor, Connection $connection): void
    {
        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $connection->close();
            copy(
                $this->sqliteDir . 'test_' . $this->snapshotIdentifier . '.snapshot.sqlite',
                $this->sqliteDir . 'test_' . $this->identifier . '.sqlite'
            );
        } else {
            $accessor->import($this->inMemoryImport);
        }
    }
}
