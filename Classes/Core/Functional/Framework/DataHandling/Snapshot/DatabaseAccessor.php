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

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder as DoctrineQueryBuilder;
use Doctrine\DBAL\Schema\Table;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder as TYPO3QueryBuilder;
use TYPO3\TestingFramework\Core\Testbase;

/**
 * @internal Use the helper methods of FunctionalTestCase
 */
class DatabaseAccessor
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return array
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
     * @param array $import
     * @throws \Doctrine\DBAL\ConnectionException
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

    /**
     * @param string $tableName
     * @return array
     */
    private function exportTable(string $tableName): array
    {
        return $this->createQueryBuilder()
            ->select('*')->from($tableName)
            ->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param string $tableName
     * @param array $columns [columnName => columnType]
     * @param array $items
     */
    private function importTable(string $tableName, array $columns, array $items)
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

    /**
     * @param string[] $columnNames
     * @param Table $table
     * @return array
     */
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

    /**
     * @return DoctrineQueryBuilder|TYPO3QueryBuilder
     */
    private function createQueryBuilder()
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        if ($queryBuilder instanceof TYPO3QueryBuilder) {
            $queryBuilder->getRestrictions()->removeAll();
        }
        return $queryBuilder;
    }
}
