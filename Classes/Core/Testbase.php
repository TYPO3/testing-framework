<?php
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
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\ClassLoadingInformation;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Schema\SchemaMigrator;
use TYPO3\CMS\Core\Database\Schema\SqlReader;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This is a helper class used by unit, functional and acceptance test
 * environment builders.
 * It contains methods to create test environments.
 *
 * This class is for internal use only and may change wihtout further notice.
 *
 * Use the classes "UnitTestCase", "FunctionalTestCase" or "AcceptanceCoreEnvironment"
 * to indirectly benefit from this class in own extensions.
 */
class Testbase
{
    /**
     * This class must be called in CLI environment as a security measure
     * against path disclosures and other stuff. Check this within
     * constructor to make sure this check can't be circumvented.
     */
    public function __construct()
    {
        // Ensure cli only as security measure
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            die('This script supports command line usage only. Please check your command.');
        }
    }

    /**
     *  Makes sure error messages during the tests get displayed no matter what is set in php.ini
     *
     * @return void
     */
    public function enableDisplayErrors()
    {
        @ini_set('display_errors', 1);
    }

    /**
     * Defines a list of basic constants that are used by GeneralUtility and other
     * helpers during tests setup. Those are sanitized in SystemEnvironmentBuilder
     * to be not defined again.
     *
     * @return void
     * @see SystemEnvironmentBuilder::defineBaseConstants()
     */
    public function defineBaseConstants()
    {
        // A linefeed, a carriage return, a CR-LF combination
        defined('LF') ?: define('LF', chr(10));
        defined('CR') ?: define('CR', chr(13));
        defined('CRLF') ?: define('CRLF', CR . LF);
    }

    /**
     * Sets $_SERVER['SCRIPT_NAME'].
     * For unit tests only
     *
     * @return void
     */
    public function defineSitePath()
    {
        $_SERVER['SCRIPT_NAME'] = $this->getWebRoot() . 'typo3/index.php';
        if (!file_exists($_SERVER['SCRIPT_NAME'])) {
            $this->exitWithMessage('Unable to determine path to entry script. Please check your path or set an environment variable \'TYPO3_PATH_ROOT\' to your root path.');
        }
    }

    /**
     * Defines the constant ORIGINAL_ROOT for the path to the original TYPO3 document root.
     * For functional / acceptance tests only
     * If ORIGINAL_ROOT already is defined, this method is a no-op.
     *
     * @return void
     */
    public function defineOriginalRootPath()
    {
        if (!defined('ORIGINAL_ROOT')) {
            define('ORIGINAL_ROOT', $this->getWebRoot());
        }

        if (!file_exists(ORIGINAL_ROOT . 'typo3/sysext/core/bin/typo3')) {
            $this->exitWithMessage('Unable to determine path to entry script. Please check your path or set an environment variable \'TYPO3_PATH_ROOT\' to your root path.');
        }
    }

    /**
     * Define TYPO3_MODE to BE
     *
     * @return void
     */
    public function defineTypo3ModeBe()
    {
        if (!defined('TYPO3_MODE')) {
            define('TYPO3_MODE', 'BE');
        }
    }

    /**
     * Sets the environment variable TYPO3_CONTEXT to testing.
     *
     * @return void
     */
    public function setTypo3TestingContext()
    {
        putenv('TYPO3_CONTEXT=Testing');
    }

    /**
     * Creates directories, recursively if required.
     *
     * @param string $directory Absolute path to directories to create
     * @return void
     * @throws Exception
     */
    public function createDirectory($directory)
    {
        if (is_dir($directory)) {
            return;
        }
        @mkdir($directory, 0777, true);
        clearstatcache();
        if (!is_dir($directory)) {
            throw new Exception('Directory "' . $directory . '" could not be created', 1404038665);
        }
    }

    /**
     * Remove test instance folder structure if it exists.
     * This may happen if a functional test before threw a fatal or is too old
     *
     * @param string $instancePath Absolute path to test instance
     * @return void
     * @throws Exception
     */
    public function removeOldInstanceIfExists($instancePath)
    {
        if (is_dir($instancePath)) {
            if (strpos($instancePath, 'typo3temp') === false) {
                // Simple safe guard to not delete something else - test instance must contain at least typo3temp
                throw new \RuntimeException(
                    'Test instance to delete must be within typo3temp',
                    1530825517
                );
            }
            $success = GeneralUtility::rmdir($instancePath, true);
            if (!$success) {
                throw new Exception(
                    'Can not remove folder: ' . $instancePath,
                    1376657210
                );
            }
        }
    }

    /**
     * Link TYPO3 CMS core from "parent" instance.
     * For functional and acceptance tests.
     *
     * @param string $instancePath Absolute path to test instance
     * @throws Exception
     * @return void
     */
    public function setUpInstanceCoreLinks($instancePath)
    {
        $linksToSet = [
            '../../../../' => $instancePath . '/typo3_src',
            'typo3_src/typo3' => $instancePath . '/typo3',
            'typo3_src/index.php' => $instancePath . '/index.php',
        ];
        chdir($instancePath);
        foreach ($linksToSet as $from => $to) {
            $success = symlink(realpath($from), $to);
            if (!$success) {
                throw new Exception(
                    'Creating link failed: from ' . $from . ' to: ' . $to,
                    1376657199
                );
            }
        }
    }

    /**
     * Link test extensions to the typo3conf/ext folder of the instance.
     * For functional and acceptance tests.
     *
     * @param string $instancePath Absolute path to test instance
     * @param array $extensionPaths Contains paths to extensions relative to document root
     * @throws Exception
     * @return void
     */
    public function linkTestExtensionsToInstance($instancePath, array $extensionPaths)
    {
        foreach ($extensionPaths as $extensionPath) {
            $absoluteExtensionPath = ORIGINAL_ROOT . $extensionPath;
            if (!is_dir($absoluteExtensionPath)) {
                throw new Exception(
                    'Test extension path ' . $absoluteExtensionPath . ' not found',
                    1376745645
                );
            }
            $destinationPath = $instancePath . '/typo3conf/ext/' . basename($absoluteExtensionPath);
            $success = symlink($absoluteExtensionPath, $destinationPath);
            if (!$success) {
                throw new Exception(
                    'Can not link extension folder: ' . $absoluteExtensionPath . ' to ' . $destinationPath,
                    1376657142
                );
            }
        }
    }

    /**
     * Link framework extensions to the typo3conf/ext folder of the instance.
     * For functional and acceptance tests.
     *
     * @param string $instancePath Absolute path to test instance
     * @param array $extensionPaths Contains paths to extensions relative to document root
     * @throws Exception
     * @return void
     */
    public function linkFrameworkExtensionsToInstance($instancePath, array $extensionPaths)
    {
        foreach ($extensionPaths as $extensionPath) {
            $absoluteExtensionPath = $this->getPackagesPath() . '/typo3/testing-framework/' . $extensionPath;
            if (!is_dir($absoluteExtensionPath)) {
                throw new Exception(
                    'Framework extension path ' . $absoluteExtensionPath . ' not found',
                    1533626848
                );
            }
            $destinationPath = $instancePath . '/typo3conf/ext/' . basename($absoluteExtensionPath);
            $success = symlink($absoluteExtensionPath, $destinationPath);
            if (!$success) {
                throw new Exception(
                    'Can not link extension folder: ' . $absoluteExtensionPath . ' to ' . $destinationPath,
                    1533626849
                );
            }
        }
    }

    /**
     * Link paths inside the test instance, e.g. from a fixture fileadmin subfolder to the
     * test instance fileadmin folder.
     * For functional and acceptance tests.
     *
     * @param string $instancePath Absolute path to test instance
     * @param array $pathsToLinkInTestInstance Contains paths as array of source => destination in key => value pairs of folders relative to test instance root
     * @throws Exception if a source path could not be found and on failing creating the symlink
     * @return void
     */
    public function linkPathsInTestInstance($instancePath, array $pathsToLinkInTestInstance)
    {
        foreach ($pathsToLinkInTestInstance as $sourcePathToLinkInTestInstance => $destinationPathToLinkInTestInstance) {
            $sourcePath = $instancePath . '/' . ltrim($sourcePathToLinkInTestInstance, '/');
            if (!file_exists($sourcePath)) {
                throw new Exception(
                    'Path ' . $sourcePath . ' not found',
                    1476109221
                );
            }
            $destinationPath = $instancePath . '/' . ltrim($destinationPathToLinkInTestInstance, '/');
            $success = symlink($sourcePath, $destinationPath);
            if (!$success) {
                throw new Exception(
                    'Can not link the path ' . $sourcePath . ' to ' . $destinationPath,
                    1389969623
                );
            }
        }
    }

    /**
     * Copies paths inside the test instance, e.g. from a fixture fileadmin
     * sub-folder to the test instance fileadmin folder. This method should
     * be used in case the references paths shall be modified inside the
     * testing instance which might not be possible with symbolic links.
     *
     * For functional and acceptance tests.
     *
     * @param string $instancePath
     * @param array $pathsToProvideInTestInstance
     * @throws Exception
     */
    public function providePathsInTestInstance(string $instancePath, array $pathsToProvideInTestInstance)
    {
        foreach ($pathsToProvideInTestInstance as $sourceIdentifier => $designationIdentifier) {
            $sourcePath = $instancePath . '/' . ltrim($sourceIdentifier, '/');
            if (!file_exists($sourcePath)) {
                throw new Exception(
                    'Path ' . $sourcePath . ' not found',
                    1511956084
                );
            }
            $destinationPath = $instancePath . '/' . ltrim($designationIdentifier, '/');
            $destinationParentPath = dirname($destinationPath);
            if (is_file($sourcePath)) {
                if (!is_dir($destinationParentPath)) {
                    // Create parent dir if it does not exist yet
                    mkdir($destinationParentPath, 0775, true);
                }
                $success = copy($sourcePath, $destinationPath);
            } else {
                $success = $this->copyRecursive($sourcePath, $destinationPath);
            }
            if (!$success) {
                throw new Exception(
                    'Can not copy the path ' . $sourcePath . ' to ' . $destinationPath,
                    1511956085
                );
            }
        }
    }

    /**
     * Database settings for functional and acceptance tests can be either set by
     * environment variables (recommended), or from an existing LocalConfiguration as fallback.
     * The method fetches these.
     *
     * An unique name will be added to the database name later.
     *
     * @param array $config Incoming config arguments, used especially in acceptance test setups
     * @throws Exception
     * @return array [DB][host], [DB][username], ...
     */
    public function getOriginalDatabaseSettingsFromEnvironmentOrLocalConfiguration(array $config = [])
    {
        $databaseName = mb_strtolower(trim($config['typo3DatabaseName'] ?? getenv('typo3DatabaseName')));
        $databaseHost = trim($config['typo3DatabaseHost'] ?? getenv('typo3DatabaseHost'));
        $databaseUsername = trim($config['typo3DatabaseUsername'] ?? getenv('typo3DatabaseUsername'));
        $databasePassword = $config['typo3DatabasePassword'] ?? getenv('typo3DatabasePassword');
        $databasePasswordTrimmed = trim($databasePassword);
        $databasePort = trim($config['typo3DatabasePort'] ?? getenv('typo3DatabasePort'));
        $databaseSocket = trim($config['typo3DatabaseSocket'] ?? getenv('typo3DatabaseSocket'));
        $databaseDriver = trim($config['typo3DatabaseDriver'] ?? getenv('typo3DatabaseDriver'));
        $databaseCharset = trim($config['typo3DatabaseCharset'] ?? getenv('typo3DatabaseCharset'));
        if ($databaseName || $databaseHost || $databaseUsername || $databasePassword || $databasePort || $databaseSocket || $databaseDriver || $databaseCharset) {
            // Try to get database credentials from environment variables first
            $originalConfigurationArray = [
                'DB' => [
                    'Connections' => [
                        'Default' => [
                            'driver' => 'mysqli'
                        ],
                    ],
                ],
            ];
            if ($databaseName) {
                $originalConfigurationArray['DB']['Connections']['Default']['dbname'] = $databaseName;
            }
            if ($databaseHost) {
                $originalConfigurationArray['DB']['Connections']['Default']['host'] = $databaseHost;
            }
            if ($databaseUsername) {
                $originalConfigurationArray['DB']['Connections']['Default']['user'] = $databaseUsername;
            }
            if ($databasePassword !== false) {
                $originalConfigurationArray['DB']['Connections']['Default']['password'] = $databasePasswordTrimmed;
            }
            if ($databasePort) {
                $originalConfigurationArray['DB']['Connections']['Default']['port'] = $databasePort;
            }
            if ($databaseSocket) {
                $originalConfigurationArray['DB']['Connections']['Default']['unix_socket'] = $databaseSocket;
            }
            if ($databaseDriver) {
                $originalConfigurationArray['DB']['Connections']['Default']['driver'] = $databaseDriver;
            }
            if ($databaseCharset) {
                $originalConfigurationArray['DB']['Connections']['Default']['charset'] = $databaseCharset;
            }
        } elseif (file_exists(ORIGINAL_ROOT . 'typo3conf/LocalConfiguration.php')) {
            // See if a LocalConfiguration file exists in "parent" instance to get db credentials from
            $originalConfigurationArray = require ORIGINAL_ROOT . 'typo3conf/LocalConfiguration.php';
        } else {
            throw new Exception(
                'Database credentials for tests are neither set through environment'
                . ' variables, and can not be found in an existing LocalConfiguration file',
                1397406356
            );
        }
        return $originalConfigurationArray['DB'];
    }

    /**
     * Maximum length of database names is 64 chars in mysql. Test this is not exceeded
     * after a suffix has been added.
     *
     * @param string $originalDatabaseName Base name of the database
     * @param array $configuration "LocalConfiguration" array with DB settings
     * @throws Exception
     */
    public function testDatabaseNameIsNotTooLong($originalDatabaseName, array $configuration)
    {
        // Maximum database name length for mysql is 64 characters
        if (strlen($configuration['DB']['Connections']['Default']['dbname']) > 64) {
            $suffixLength = strlen($configuration['DB']['Connections']['Default']['dbname']) - strlen($originalDatabaseName);
            $maximumOriginalDatabaseName = 64 - $suffixLength;
            throw new Exception(
                'The name of the database that is used for the functional test (' . $originalDatabaseName . ')' .
                ' exceeds the maximum length of 64 character allowed by MySQL. You have to shorten your' .
                ' original database name to ' . $maximumOriginalDatabaseName . ' characters',
                1377600104
            );
        }
    }

    /**
     * Create LocalConfiguration.php file of the test instance.
     * For functional and acceptance tests.
     *
     * @param string $instancePath Absolute path to test instance
     * @param array $configuration Base configuration array
     * @param array $overruleConfiguration Overrule factory and base configuration
     * @throws Exception
     * @return void
     */
    public function setUpLocalConfiguration($instancePath, array $configuration, array $overruleConfiguration)
    {
        // Base of final LocalConfiguration is core factory configuration
        $finalConfigurationArray = require ORIGINAL_ROOT . 'typo3/sysext/core/Configuration/FactoryConfiguration.php';
        $finalConfigurationArray = array_replace_recursive($finalConfigurationArray, $configuration);
        $finalConfigurationArray = array_replace_recursive($finalConfigurationArray, $overruleConfiguration);
        $result = $this->writeFile(
            $instancePath . '/typo3conf/LocalConfiguration.php',
            '<?php' . chr(10) .
            'return ' .
            ArrayUtility::arrayExport(
                $finalConfigurationArray
            ) .
            ';'
        );
        if (!$result) {
            throw new Exception('Can not write local configuration', 1376657277);
        }
    }

    /**
     * Compile typo3conf/PackageStates.php containing default packages like core,
     * a test specific list of additional core extensions, and a list of
     * test extensions.
     * For functional and acceptance tests.
     *
     * @param string $instancePath Absolute path to test instance
     * @param array $defaultCoreExtensionsToLoad Default list of core extensions to load
     * @param array $additionalCoreExtensionsToLoad Additional core extensions to load
     * @param array $testExtensionPaths Paths to test extensions relative to document root
     * @param array $frameworkExtensionPaths Paths to framework extensions relative to testing framework package
     * @throws Exception
     */
    public function setUpPackageStates(
        $instancePath,
        array $defaultCoreExtensionsToLoad,
        array $additionalCoreExtensionsToLoad,
        array $testExtensionPaths,
        array $frameworkExtensionPaths
    ) {
        $packageStates = [
            'packages' => [],
            'version' => 5,
        ];

        // Register default list of extensions and set active
        foreach ($defaultCoreExtensionsToLoad as $extensionName) {
            $packageStates['packages'][$extensionName] = [
                'packagePath' => 'typo3/sysext/' . $extensionName . '/'
            ];
        }

        // Register additional core extensions and set active
        foreach ($additionalCoreExtensionsToLoad as $extensionName) {
            $packageStates['packages'][$extensionName] = [
                'packagePath' => 'typo3/sysext/' . $extensionName . '/'
            ];
        }

        // Activate test extensions that have been symlinked before
        foreach ($testExtensionPaths as $extensionPath) {
            $extensionName = basename($extensionPath);
            $packageStates['packages'][$extensionName] = [
                'packagePath' => 'typo3conf/ext/' . $extensionName . '/'
            ];
        }

        // Activate framework extensions that have been symlinked before
        foreach ($frameworkExtensionPaths as $extensionPath) {
            $extensionName = basename($extensionPath);
            $packageStates['packages'][$extensionName] = [
                'packagePath' => 'typo3conf/ext/' . $extensionName . '/'
            ];
        }

        $result = $this->writeFile(
            $instancePath . '/typo3conf/PackageStates.php',
            '<?php' . chr(10) .
            'return ' .
            ArrayUtility::arrayExport(
                $packageStates
            ) .
            ';'
        );

        if (!$result) {
            throw new Exception('Can not write PackageStates', 1381612729);
        }
    }

    /**
     * Create a low level connection to dbms, without selecting the target database.
     * Drop existing database if it exists and create a new one.
     *
     * @param string $databaseName Database name of this test instance
     * @param string $originalDatabaseName Original database name before suffix was added
     * @throws \TYPO3\TestingFramework\Core\Exception
     * @return void
     */
    public function setUpTestDatabase(string $databaseName, string $originalDatabaseName): void
    {
        // First close existing connections from a possible previous test case and
        // tell our ConnectionPool there are no current connections anymore.
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->close();
        $connectionPool->resetConnections();

        // Drop database if exists. Directly using the Doctrine DriverManager to
        // work around connection caching in ConnectionPool.
        // @todo: This should by now work with using "our" ConnectionPool again, it does now, though.
        $connectionParameters = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default'];
        unset($connectionParameters['dbname']);
        $schemaManager = DriverManager::getConnection($connectionParameters)->getSchemaManager();

        if ($schemaManager->getDatabasePlatform()->getName() === 'sqlite') {
            // This is the "path" option in sqlite: one file = one db
            $schemaManager->dropDatabase($databaseName);
        } elseif (in_array($databaseName, $schemaManager->listDatabases(), true)) {
            // Suppress listDatabases() call on sqlite which is not implemented there, but
            // check db existence on all other platforms before drop call
            $schemaManager->dropDatabase($databaseName);
        }
        try {
            $schemaManager->createDatabase($databaseName);
        } catch (DBALException $e) {
            $user = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'];
            $host = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'];
            throw new Exception(
                'Unable to create database with name ' . $databaseName . '. This is probably a permission problem.'
                . ' For this instance this could be fixed executing:'
                . ' GRANT ALL ON `' . $originalDatabaseName . '_%`.* TO `' . $user . '`@`' . $host . '`;'
                . ' Original message thrown by database layer: ' . $e->getMessage(),
                1376579070
            );
        }
    }

    /**
     * Bootstrap basic TYPO3. This bootstraps TYPO3 far enough to initialize database afterwards.
     * For functional and acceptance tests.
     *
     * @param string $instancePath Absolute path to test instance
     * @return void
     */
    public function setUpBasicTypo3Bootstrap($instancePath)
    {
        $_SERVER['PWD'] = $instancePath;
        $_SERVER['argv'][0] = 'index.php';

        // Reset state from a possible previous run
        GeneralUtility::purgeInstances();
        GeneralUtility::resetApplicationContext();

        $classLoader = require __DIR__ . '/../../../../autoload.php';
        SystemEnvironmentBuilder::run(0, SystemEnvironmentBuilder::REQUESTTYPE_BE | SystemEnvironmentBuilder::REQUESTTYPE_CLI);
        Bootstrap::init($classLoader);
        // Make sure output is not buffered, so command-line output can take place and
        // phpunit does not whine about changed output bufferings in tests.
        ob_end_clean();

        $this->dumpClassLoadingInformation();
    }

    /**
     * Dump class loading information
     *
     * @return void
     */
    public function dumpClassLoadingInformation()
    {
        if (!ClassLoadingInformation::isClassLoadingInformationAvailable()) {
            ClassLoadingInformation::dumpClassLoadingInformation();
            ClassLoadingInformation::registerClassLoadingInformation();
        }
    }

    /**
     * Truncate all tables.
     * For functional and acceptance tests.
     *
     * @throws Exception
     * @return void
     */
    public function initializeTestDatabaseAndTruncateTables()
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $schemaManager = $connection->getSchemaManager();
        foreach ($schemaManager->listTables() as $table) {
            $connection->truncate($table->getName());
            self::resetTableSequences($connection, $table->getName());
        }
    }

    /**
     * Load ext_tables.php files.
     * For functional and acceptance tests.
     *
     * @return void
     */
    public function loadExtensionTables()
    {
        Bootstrap::loadExtTables();
    }

    /**
     * Create tables and import static rows.
     * For functional and acceptance tests.
     *
     * @return void
     */
    public function createDatabaseStructure()
    {
        $schemaMigrationService = GeneralUtility::makeInstance(SchemaMigrator::class);
        $sqlReader = GeneralUtility::makeInstance(SqlReader::class);
        $sqlCode = $sqlReader->getTablesDefinitionString(true);

        $createTableStatements = $sqlReader->getCreateTableStatementArray($sqlCode);

        $schemaMigrationService->install($createTableStatements);

        $insertStatements = $sqlReader->getInsertStatementArray($sqlCode);
        $schemaMigrationService->importStaticData($insertStatements);
    }

    /**
     * Imports a data set represented as XML into the test database,
     *
     * @param string $path Absolute path to the XML file containing the data set to load
     * @return void
     * @throws \Doctrine\DBAL\DBALException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function importXmlDatabaseFixture($path)
    {
        $path = $this->resolvePath($path);
        if (!is_file($path)) {
            throw new \RuntimeException(
                'Fixture file ' . $path . ' not found',
                1376746261
            );
        }

        $fileContent = file_get_contents($path);
        // Disables the functionality to allow external entities to be loaded when parsing the XML, must be kept
        $previousValueOfEntityLoader = libxml_disable_entity_loader(true);
        $xml = simplexml_load_string($fileContent);
        libxml_disable_entity_loader($previousValueOfEntityLoader);
        $foreignKeys = [];

        /** @var $table \SimpleXMLElement */
        foreach ($xml->children() as $table) {
            $insertArray = [];

            /** @var $column \SimpleXMLElement */
            foreach ($table->children() as $column) {
                $columnName = $column->getName();
                $columnValue = null;

                if (isset($column['ref'])) {
                    list($tableName, $elementId) = explode('#', $column['ref']);
                    $columnValue = $foreignKeys[$tableName][$elementId];
                } elseif (isset($column['is-NULL']) && ($column['is-NULL'] === 'yes')) {
                    $columnValue = null;
                } else {
                    $columnValue = (string)$table->$columnName;
                }

                $insertArray[$columnName] = $columnValue;
            }

            $tableName = $table->getName();
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);

            // With mssql, hard setting uid auto-increment primary keys is only allowed if
            // the table is prepared for such an operation beforehand
            $platform = $connection->getDatabasePlatform();
            $sqlServerIdentityDisabled = false;
            if ($platform instanceof SQLServerPlatform) {
                try {
                    $connection->exec('SET IDENTITY_INSERT ' . $tableName . ' ON');
                    $sqlServerIdentityDisabled = true;
                } catch (\Doctrine\DBAL\DBALException $e) {
                    // Some tables like sys_refindex don't have an auto-increment uid field and thus no
                    // IDENTITY column. Instead of testing existance, we just try to set IDENTITY ON
                    // and catch the possible error that occurs.
                }
            }

            // Some DBMS like mssql are picky about inserting blob types with correct cast, setting
            // types correctly (like Connection::PARAM_LOB) allows doctrine to create valid SQL
            $types = [];
            $tableDetails = $connection->getSchemaManager()->listTableDetails($tableName);
            foreach ($insertArray as $columnName => $columnValue) {
                $types[] = $tableDetails->getColumn($columnName)->getType()->getBindingType();
            }

            // Insert the row
            $connection->insert($tableName, $insertArray, $types);

            if ($sqlServerIdentityDisabled) {
                // Reset identity if it has been changed
                $connection->exec('SET IDENTITY_INSERT ' . $tableName . ' OFF');
            }

            static::resetTableSequences($connection, $tableName);

            if (isset($table['id'])) {
                $elementId = (string)$table['id'];
                $foreignKeys[$tableName][$elementId] = $connection->lastInsertId($tableName);
            }
        }
    }

    /**
     * Perform post processing of database tables after an insert has been performed.
     * Doing this once per insert is rather slow, but due to the soft reference behavior
     * this needs to be done after every row to ensure consistent results.
     *
     * @param \TYPO3\CMS\Core\Database\Connection $connection
     * @param string $tableName
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function resetTableSequences(Connection $connection, string $tableName)
    {
        $platform = $connection->getDatabasePlatform();
        if ($platform instanceof PostgreSqlPlatform) {
            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder->getRestrictions()->removeAll();
            $row = $queryBuilder->select('PGT.schemaname', 'S.relname', 'C.attname', 'T.relname AS tablename')
                ->from('pg_class', 'S')
                ->from('pg_depend', 'D')
                ->from('pg_class', 'T')
                ->from('pg_attribute', 'C')
                ->from('pg_tables', 'PGT')
                ->where(
                    $queryBuilder->expr()->eq('S.relkind', $queryBuilder->quote('S')),
                    $queryBuilder->expr()->eq('S.oid', $queryBuilder->quoteIdentifier('D.objid')),
                    $queryBuilder->expr()->eq('D.refobjid', $queryBuilder->quoteIdentifier('T.oid')),
                    $queryBuilder->expr()->eq('D.refobjid', $queryBuilder->quoteIdentifier('C.attrelid')),
                    $queryBuilder->expr()->eq('D.refobjsubid', $queryBuilder->quoteIdentifier('C.attnum')),
                    $queryBuilder->expr()->eq('T.relname', $queryBuilder->quoteIdentifier('PGT.tablename')),
                    $queryBuilder->expr()->eq('PGT.tablename', $queryBuilder->quote($tableName))
                )
                ->setMaxResults(1)
                ->execute()
                ->fetch();

            if ($row !== false) {
                $connection->exec(
                    sprintf(
                        'SELECT SETVAL(%s, COALESCE(MAX(%s), 0)+1, FALSE) FROM %s',
                        $connection->quote($row['schemaname'] . '.' . $row['relname']),
                        $connection->quoteIdentifier($row['attname']),
                        $connection->quoteIdentifier($row['schemaname'] . '.' . $row['tablename'])
                    )
                );
            }
        } elseif ($platform instanceof SqlitePlatform) {
            // Drop eventually existing sqlite sequence for this table
            $connection->exec(
                sprintf(
                    'DELETE FROM sqlite_sequence WHERE name=%s',
                    $connection->quote($tableName)
                )
            );
        }
    }

    /**
     * Copy a directory structure $from a source $to a destination,
     *
     * @param $from Absolute source path
     * @param $to Absolute target path
     * @return bool True if all went well
     */
    protected function copyRecursive($from, $to) {
        $dir = opendir($from);
        if (!file_exists($to)) {
            mkdir($to, 0775, true);
        }
        $result = true;
        while (false !== ($file = readdir($dir))) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (is_dir($from . DIRECTORY_SEPARATOR . $file)) {
                $success = $this->copyRecursive($from . DIRECTORY_SEPARATOR . $file, $to . DIRECTORY_SEPARATOR . $file);
                $result = $result & $success;
            }
            else {
                $success = copy($from . DIRECTORY_SEPARATOR . $file, $to . DIRECTORY_SEPARATOR . $file);
                $result = $result & $success;
            }
        }
        closedir($dir);
        return $result;
    }

    /**
     * Get Path to vendor dir
     * Since we are installed in vendor dir, we can safely assume the path of the vendor
     * directory relative to this file
     *
     * @return string Absolute path to vendor dir, without trailing slash
     */
    public function getPackagesPath(): string
    {
        return rtrim(strtr(dirname(dirname(dirname(dirname(__DIR__)))), '\\', '/'), '/');
    }

    /**
     * Returns the absolute path the TYPO3 document root.
     * This is the "original" document root, not the "instance" root for functional / acceptance tests.
     *
     * @return string the TYPO3 document root using Unix path separators
     */
    public function getWebRoot()
    {
        if (getenv('TYPO3_PATH_ROOT')) {
            $webRoot = getenv('TYPO3_PATH_ROOT');
        } else {
            // If doing casual extension testing, env var TYPO3_PATH_ROOT is *always* set
            // through the composer autoload-include.php file created by cms-composer-installer.
            // This fallback here is for native core (typo3/cms package) tests, where
            // cms-composer-installer does not create that file since that package is also
            // used for packaging non-composer instances.
            $webRoot = getcwd();
        }
        return rtrim(strtr($webRoot, '\\', '/'), '/') . '/';
    }

    /**
     * Send http headers, echo out a text message and exit with error code
     *
     * @param string $message
     */
    protected function exitWithMessage($message)
    {
        echo $message . chr(10);
        exit(1);
    }

    protected function resolvePath(string $path)
    {
        if (strpos($path, 'EXT:') === 0) {
            $path = GeneralUtility::getFileAbsFileName($path);
        } elseif (strpos($path, 'PACKAGE:') === 0) {
            $path = $this->getPackagesPath() . '/' . str_replace('PACKAGE:', '',$path);
        }
        return $path;
    }

    /**
     * Writes $content to the file $file. This is a simplified version
     * of GeneralUtility::writeFile that does not fix permissions.
     *
     * @param string $file Filepath to write to
     * @param string $content Content to write
     * @return bool TRUE if the file was successfully opened and written to.
     */
    protected function writeFile($file, $content)
    {
        if ($fd = fopen($file, 'wb')) {
            $res = fwrite($fd, $content);
            fclose($fd);
            if ($res === false) {
                return false;
            }
            return true;
        }
        return false;
    }
}
