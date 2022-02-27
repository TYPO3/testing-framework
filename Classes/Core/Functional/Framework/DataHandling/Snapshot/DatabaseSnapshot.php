<?php

declare(strict_types=1);
namespace TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Snapshot;

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

use Doctrine\DBAL\Connection;

/**
 * @internal Use FunctionalTestCase->withDatabaseSnapshot() to leverage this.
 */
class DatabaseSnapshot
{
    /**
     * Data up to 10 MiB is kept in memory
     */
    private const VALUE_IN_MEMORY_THRESHOLD = 1024**2 * 10;

    private static $instance;
    private $sqliteDir;
    private $identifier;
    private $inMemoryImport;

    public static function initialize(string $sqliteDir, string $identifier): void
    {
        self::$instance = new self($sqliteDir, $identifier);
    }

    public static function instance(): self
    {
        return self::$instance;
    }

    private function __construct(string $sqliteDir, string $identifier)
    {
        $this->identifier = $identifier;
        $this->sqliteDir = $sqliteDir;
        $this->inMemoryImport = [];
    }

    public function create(DatabaseAccessor $accessor, Connection $connection): void
    {
        if ($connection->getDatabasePlatform()->getName() === 'sqlite') {
            $connection->close();
            copy(
                $this->sqliteDir . 'test_' . $this->identifier . '.sqlite',
                $this->sqliteDir . 'test_' . $this->identifier . '.snapshot.sqlite'
            );
            $this->inMemoryImport = [true];
        } else {
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

    public function restore(DatabaseAccessor $accessor, Connection $connection): void
    {
        if ($connection->getDatabasePlatform()->getName() === 'sqlite') {
            $connection->close();
            copy(
                $this->sqliteDir . 'test_' . $this->identifier . '.snapshot.sqlite',
                $this->sqliteDir . 'test_' . $this->identifier . '.sqlite'
            );
        } else {
            if (!is_array($this->inMemoryImport)) {
                throw new \RuntimeException('Invalid import data', 1535487372);
            }
            $accessor->import($this->inMemoryImport);
        }
    }
}
