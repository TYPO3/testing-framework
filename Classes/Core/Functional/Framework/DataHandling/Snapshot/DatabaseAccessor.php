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

use Doctrine\DBAL\Schema\Table;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder as TYPO3QueryBuilder;
use TYPO3\TestingFramework\Core\Testbase;

/**
 * Helper class of DatabaseSnapshot doing the main query
 * work around snapshow handling.
 *
 * @internal Use FunctionalTestCase->withDatabaseSnapshot() to leverage this.
 */
class DatabaseAccessor
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Fetch rows from all tables and return as array.
     */
    public function export(): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        $export = [];
        foreach ($schemaManager->listTables() as $table) {
            $tableName = $table->getName();
            if (stripos($tableName, 'cf_') === 0 || stripos($tableName, 'cache_') === 0) {
                continue;
            }
            $tableExport = $this->exportTable($tableName);
            if (empty($tableExport)) {
                continue;
            }
            $export[] = [
                'tableName' => $tableName,
                'columns' => $this->prepareColumns(
                    array_keys($tableExport[0]),
                    $table
                ),
                'items' => array_map('array_values', $tableExport),
            ];
        }
        return $export;
    }

    /**
     * Import rows to database from rows created by export().
     */
    public function import(array $import)
    {
        foreach ($import as $tableImport) {
            $this->importTable(
                $tableImport['tableName'] ?? '',
                $tableImport['columns'] ?? [],
                $tableImport['items'] ?? []
            );
        }
    }

    private function exportTable(string $tableName): array
    {
        return $this->createQueryBuilder()
            ->select('*')->from($tableName)
            ->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param array $columns [columnName => columnType]
     */
    private function importTable(string $tableName, array $columns, array $items): void
    {
        if (empty($tableName) || empty($columns) || empty($items)) {
            throw new \RuntimeException(
                'Invalid import data',
                1535487373
            );
        }

        $columnNames = array_keys($columns);
        foreach ($items as $item) {
            $this->connection->insert(
                $tableName,
                array_combine($columnNames, $item),
                $columns
            );
        }
        // reset table sequences after inserting snapshot data. Dataset contains primary key column data which
        // leads to out-of-sync sequence values for some dbms platforms, thus resetting sequence values
        // is needed.
        Testbase::resetTableSequences($this->connection, $tableName);
    }

    private function prepareColumns(array $columnNames, Table $table): array
    {
        $columnTypes = array_map(
            function (string $columnName) use ($table) {
                return $table->getColumn($columnName)
                    ->getType()
                    ->getBindingType();
            },
            $columnNames
        );
        return array_combine($columnNames, $columnTypes);
    }

    private function createQueryBuilder(): TYPO3QueryBuilder
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder;
    }
}
