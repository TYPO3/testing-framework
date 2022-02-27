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
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Query\QueryBuilder as DoctrineQueryBuilder;
use Doctrine\DBAL\Schema\Table;
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
        $schemaManager = $this->connection->getSchemaManager();

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
            ->execute()->fetchAll(FetchMode::ASSOCIATIVE);
    }

    /**
     * @param string $tableName
     * @param array $columns [columnName => columnType]
     * @param array $items
     *
     * @see \TYPO3\TestingFramework\Core\DatabaseConnectionWrapper handling IDENTITY_INSERT
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
            try {
                $this->connection->insert(
                    $tableName,
                    array_combine($columnNames, $item),
                    $columns
                );
            } catch (UniqueConstraintViolationException | DBALException $e) {
                // @todo: DBALException is used here for mssql only, others throw UniqueConstraintViolationException
                // @todo: At least switch from deprecated DBALException to \Doctrine\DBAL\Exception, when TF is v11 and higher.
                // The scenario solved here: Some tests (eg. ClipboardTest) use the snapshot *after* first rows have
                // been inserted in setUp(). Those rows are snapshotted too, the second test then tries to insert
                // those rows from the snapshot again. But they exist already, which leads to an exception.
                // This can't be solved easily, especially due to setUpBackendUserFromFixture(), which both inserts
                // rows, plus sets up PHP state, and DataHandlerWriter::withBackendUser() depends on this state too,
                // which is used *within* the snapshot callback. We thus can't get rid of this unfortunate backend user
                // handling right now.
                // The previous solution was to simply truncate all tables before rows are inserted from the snapshot.
                // But this is slow. The solution is now to simply let the insert fail with an exception, then truncate,
                // then insert again. This avoids lots of truncate calls.
                // @todo: Find a solution for the backend user handling and remove this catch block altogether.
                // @deprecated: This catch block will be removed when the TF-API handles it in a better way. In general,
                //              when using the snapshotter, no test should add rows before import().
                $this->connection->truncate($tableName);
                $this->connection->insert(
                    $tableName,
                    array_combine($columnNames, $item),
                    $columns
                );
            }
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
