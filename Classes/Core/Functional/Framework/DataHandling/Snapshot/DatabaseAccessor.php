<?php
declare(strict_types = 1);
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

use Doctrine\DBAL\FetchMode;
use TYPO3\CMS\Core\Database\Connection;

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
        $export = [];
        $tableNames = array_filter(
            $this->connection->getSchemaManager()->listTableNames(),
            function (string $tableName) {
                return stripos($tableName, 'cf_') !== 0;
            }
        );
        foreach ($tableNames as $tableName) {
            $tableExport = $this->exportTable($tableName);
            if (empty($tableExport)) {
                continue;
            }
            $export[] = [
                'tableName' => $tableName,
                'fieldNames' => array_keys($tableExport[0]),
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
        $this->connection->beginTransaction();

        foreach ($import as $tableImport) {
            $this->importTable(
                $tableImport['tableName'] ?? '',
                $tableImport['fieldNames'] ?? [],
                $tableImport['items'] ?? []
            );
        }

        $this->connection->commit();
    }

    /**
     * @param string $tableName
     * @return array
     */
    private function exportTable(string $tableName): array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')->from($tableName)
            ->execute()->fetchAll(FetchMode::ASSOCIATIVE);
    }

    /**
     * @param string $tableName
     * @param array $fieldNames
     * @param array $items
     *
     * @see \TYPO3\TestingFramework\Core\DatabaseConnectionWrapper handling IDENTITY_INSERT
     */
    private function importTable(string $tableName, array $fieldNames, array $items)
    {
        if (empty($tableName) || empty($fieldNames) || empty($items)) {
            throw new \RuntimeException(
                'Invalid import data',
                1535487373
            );
        }

        $this->connection->truncate($tableName);

        foreach ($items as $item) {
            $this->connection->insert(
                $tableName,
                array_combine($fieldNames, $item)
            );
        }
    }
}
