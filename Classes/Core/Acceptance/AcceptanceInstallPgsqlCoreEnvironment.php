<?php
declare(strict_types=1);
namespace TYPO3\TestingFramework\Core\Acceptance;

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

use Codeception\Event\SuiteEvent;
use Codeception\Events;
use Codeception\Extension;
use Doctrine\DBAL\DriverManager;
use TYPO3\TestingFramework\Core\Testbase;

/**
 * This codeception extension creates a basic TYPO3 instance within
 * typo3temp. It is used as a basic acceptance test that clicks through
 * the TYPO3 installation steps.
 */
class AcceptanceInstallPgsqlCoreEnvironment extends Extension
{
    /**
     * Events to listen to
     */
    public static $events = [
        Events::TEST_BEFORE => 'bootstrapTypo3Environment',
    ];

    /**
     * Handle SUITE_BEFORE event.
     *
     * Create a full standalone TYPO3 instance within typo3temp/var/tests/acceptance,
     * create a database and create database schema.
     */
    public function bootstrapTypo3Environment()
    {
        $testbase = new Testbase();
        $testbase->enableDisplayErrors();
        $testbase->defineBaseConstants();
        $testbase->defineOriginalRootPath();
        $testbase->setTypo3TestingContext();

        $instancePath = ORIGINAL_ROOT . 'typo3temp/var/tests/acceptanceinstallpgsql';
        $testbase->removeOldInstanceIfExists($instancePath);
        putenv('TYPO3_PATH_ROOT=' . $instancePath);

        // Drop db from a previous run if exists
        $connectionParameters = [
            'driver' => 'pdo_pgsql',
            'host' => '127.0.0.1',
            'password' => getenv('typo3DatabasePassword'),
            'user' => getenv('typo3DatabaseUsername'),
        ];
        if (!empty(getenv('typo3DatabasePort'))) {
            $connectionParameters['port'] = getenv('typo3DatabasePort');
        }
        $schemaManager = DriverManager::getConnection($connectionParameters)->getSchemaManager();
        $databaseName = getenv('typo3DatabaseName') . '_atipgsql';
        if (in_array($databaseName, $schemaManager->listDatabases(), true)) {
            $schemaManager->dropDatabase($databaseName);
        }
        $schemaManager->createDatabase($databaseName);

        $testbase->createDirectory($instancePath);
        $testbase->setUpInstanceCoreLinks($instancePath);
        touch($instancePath . '/FIRST_INSTALL');
    }
}
