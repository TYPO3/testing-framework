<?php

declare(strict_types=1);
namespace TYPO3\TestingFramework\Core;

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

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use TYPO3\CMS\Core\Database\Connection;

/**
 * Wrapper for database connections in order to intercept statement executions.
 */
class DatabaseConnectionWrapper extends Connection
{
    /**
     * @var bool|null
     */
    private $allowIdentityInsert;

    /**
     * Whether to allow modification of IDENTITY_INSERT for SQL Server platform.
     * + null: unspecified, decided later during runtime (based on 'uid' & $TCA)
     * + true: always allow, e.g. before actually importing data
     * + false: always deny, e.g. when importing data is finished
     *
     * @param bool|null $allowIdentityInsert
     */
    public function allowIdentityInsert(?bool $allowIdentityInsert)
    {
        $this->allowIdentityInsert = $allowIdentityInsert;
    }

    /**
     * Wraps insert execution in order to consider SQL Server IDENTITY_INSERT.
     *
     * @param string $tableName
     * @param array $data
     * @param array $types
     * @return int
     */
    public function insert($tableName, array $data, array $types = []): int
    {
        $modified = $this->shallModifyIdentityInsert($data)
            && $this->modifyIdentityInsert($tableName, true);

        $result = parent::insert($tableName, $data, $types);

        if ($modified) {
            $this->modifyIdentityInsert($tableName, false);
        }

        return $result;
    }

    /**
     * @param array $data
     * @return bool
     */
    private function shallModifyIdentityInsert(array $data): bool
    {
        if ($this->allowIdentityInsert !== null) {
            return $this->allowIdentityInsert;
        }
        return isset($data['uid']);
    }

    /**
     * In SQL Server (MSSQL), hard setting uid auto-increment primary keys is
     * only allowed if the table is prepared for such an operation beforehand.
     * This method has to be invoked explicitly before INSERT statements in
     * order to enable the behavior and has to be disabled afterwards again.
     *
     * Quotation of SQL Server SET IDENTITY_INSERT (Transact-SQL) documentation:
     * > At any time, only one table in a session can have the IDENTITY_INSERT
     * > property set to ON. If a table already has this property set to ON,
     * > and a SET IDENTITY_INSERT ON statement is issued for another table,
     * > SQL Server returns an error message that states SET IDENTITY_INSERT
     * > is already ON and reports the table it is set ON for.
     *
     * @param string $tableName Table name to be modified
     * @param bool $enable Whether to enable ('ON') or disable ('OFF')
     * @return bool Whether executed statement has be successful
     */
    private function modifyIdentityInsert(string $tableName, bool $enable): bool
    {
        try {
            $platform = $this->getDatabasePlatform();
        } catch (DBALException $exception) {
            return false;
        }

        if (!$platform instanceof SQLServerPlatform) {
            return false;
        }

        try {
            $statement = sprintf(
                'SET IDENTITY_INSERT %s %s',
                $tableName,
                $enable ? 'ON' : 'OFF'
            );
            $this->exec($statement);
            return true;
        } catch (DBALException $e) {
            // Some tables like sys_refindex don't have an auto-increment uid field and thus no
            // IDENTITY column. Instead of testing existance, we just try to set IDENTITY ON
            // and catch the possible error that occurs.
        }

        return false;
    }
}
