<?php
namespace TYPO3\TestingFramework\Core\Functional;

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
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use PHPUnit\Util\PHP\AbstractPhpProcess;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\Backend\NullBackend;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\BaseTestCase;
use TYPO3\TestingFramework\Core\DatabaseConnectionWrapper;
use TYPO3\TestingFramework\Core\Exception;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\DataSet;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Snapshot\DatabaseAccessor;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Snapshot\DatabaseSnapshot;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalResponse;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalResponseException;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Response;
use TYPO3\TestingFramework\Core\Testbase;

/**
 * Base test case class for functional tests, all TYPO3 CMS
 * functional tests should extend from this class!
 *
 * If functional tests need additional setUp() and tearDown() code,
 * they *must* call parent::setUp() and parent::tearDown() to properly
 * set up and destroy the test system.
 *
 * The functional test system creates a full new TYPO3 CMS instance
 * within typo3temp/ of the base system and the bootstraps this TYPO3 instance.
 * This abstract class takes care of creating this instance with its
 * folder structure and a LocalConfiguration, creates an own database
 * for each test run and imports tables of loaded extensions.
 *
 * Functional tests must be run standalone (calling native phpunit
 * directly) and can not be executed by eg. the ext:phpunit backend module.
 * Additionally, the script must be called from the document root
 * of the instance, otherwise path calculation is not successfully.
 *
 * Call whole functional test suite, example:
 * - cd /var/www/t3master/foo  # Document root of CMS instance, here is index.php of frontend
 * - typo3/../bin/phpunit -c components/testing_framework/core/Build/FunctionalTests.xml
 *
 * Call single test case, example:
 * - cd /var/www/t3master/foo  # Document root of CMS instance, here is index.php of frontend
 * - typo3/../bin/phpunit \
 *     --process-isolation \
 *     --bootstrap components/testing_framework/core/Build/FunctionalTestsBootstrap.php \
 *     typo3/sysext/core/Tests/Functional/DataHandling/DataHandlerTest.php
 */
abstract class FunctionalTestCase extends BaseTestCase
{
    /**
     * An unique identifier for this test case. Location of the test
     * instance and database name depend on this. Calculated early in setUp()
     *
     * @var string
     */
    protected $identifier;

    /**
     * Absolute path to test instance document root. Depends on $identifier.
     * Calculated early in setUp()
     *
     * @var string
     */
    protected $instancePath;

    /**
     * Core extensions to load.
     *
     * If the test case needs additional core extensions as requirement,
     * they can be noted here and will be added to LocalConfiguration
     * extension list and ext_tables.sql of those extensions will be applied.
     *
     * This property will stay empty in this abstract, so it is possible
     * to just overwrite it in extending classes. Extensions noted here will
     * be loaded for every test of a test case and it is not possible to change
     * the list of loaded extensions between single tests of a test case.
     *
     * A default list of core extensions is always loaded.
     *
     * @see FunctionalTestCaseUtility $defaultActivatedCoreExtensions
     * @var array
     */
    protected $coreExtensionsToLoad = [];

    /**
     * Array of test/fixture extensions paths that should be loaded for a test.
     *
     * This property will stay empty in this abstract, so it is possible
     * to just overwrite it in extending classes. Extensions noted here will
     * be loaded for every test of a test case and it is not possible to change
     * the list of loaded extensions between single tests of a test case.
     *
     * Given path is expected to be relative to your document root, example:
     *
     * array(
     *   'typo3conf/ext/some_extension/Tests/Functional/Fixtures/Extensions/test_extension',
     *   'typo3conf/ext/base_extension',
     * );
     *
     * Extensions in this array are linked to the test instance, loaded
     * and their ext_tables.sql will be applied.
     *
     * @var string[]
     */
    protected $testExtensionsToLoad = [];

    /**
     * Same as $testExtensionsToLoad, but included per default from the testing framework.
     *
     * @var string[]
     */
    protected $frameworkExtensionsToLoad = [
        'Resources/Core/Functional/Extensions/json_response',
    ];

    /**
     * Array of test/fixture folder or file paths that should be linked for a test.
     *
     * This property will stay empty in this abstract, so it is possible
     * to just overwrite it in extending classes. Path noted here will
     * be linked for every test of a test case and it is not possible to change
     * the list of folders between single tests of a test case.
     *
     * array(
     *   'link-source' => 'link-destination'
     * );
     *
     * Given paths are expected to be relative to the test instance root.
     * The array keys are the source paths and the array values are the destination
     * paths, example:
     *
     * [
     *   'typo3/sysext/impext/Tests/Functional/Fixtures/Folders/fileadmin/user_upload' =>
     *   'fileadmin/user_upload',
     * ]
     *
     * To be able to link from my_own_ext the extension path needs also to be registered in
     * property $testExtensionsToLoad
     *
     * @var string[]
     */
    protected $pathsToLinkInTestInstance = [];

    /**
     * Similar to $pathsToLinkInTestInstance, with the difference that given
     * paths are really duplicated and provided in the instance - instead of
     * using symbolic links. Examples:
     *
     * [
     *   // Copy an entire directory recursive to fileadmin
     *   'typo3/sysext/lowlevel/Tests/Functional/Fixtures/testImages/' => 'fileadmin/',
     *   // Copy a single file into some deep destination directory
     *   'typo3/sysext/lowlevel/Tests/Functional/Fixtures/testImage/someImage.jpg' => 'fileadmin/_processed_/0/a/someImage.jpg',
     * ]
     *
     * @var string[]
     */
    protected $pathsToProvideInTestInstance = [];

    /**
     * This configuration array is merged with TYPO3_CONF_VARS
     * that are set in default configuration and factory configuration
     *
     * @var array
     */
    protected $configurationToUseInTestInstance = [];

    /**
     * Array of folders that should be created inside the test instance document root.
     *
     * This property will stay empty in this abstract, so it is possible
     * to just overwrite it in extending classes. Path noted here will
     * be linked for every test of a test case and it is not possible to change
     * the list of folders between single tests of a test case.
     *
     * Per default the following folder are created
     * /fileadmin
     * /typo3temp
     * /typo3conf
     * /typo3conf/ext
     *
     * To create additional folders add the paths to this array. Given paths are expected to be
     * relative to the test instance root and have to begin with a slash. Example:
     *
     * [
     *   'fileadmin/user_upload'
     * ]
     *
     * @var array
     */
    protected $additionalFoldersToCreate = [];

    /**
     * The fixture which is used when initializing a backend user
     *
     * @var string
     */
    protected $backendUserFixture = 'PACKAGE:typo3/testing-framework/Resources/Core/Functional/Fixtures/be_users.xml';

    /**
     * This internal variable tracks if the given test is the first test of
     * that test case. This variable is set to current calling test case class.
     * Consecutive tests then optimize and do not create a full
     * database structure again but instead just truncate all tables which
     * is much quicker.
     *
     * @var string
     */
    private static $currestTestCaseClass;

    /**
     * Set up creates a test instance and database.
     *
     * This method should be called with parent::setUp() in your test cases!
     *
     * @return void
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function setUp(): void
    {
        if (!defined('ORIGINAL_ROOT')) {
            $this->markTestSkipped('Functional tests must be called through phpunit on CLI');
        }

        $this->identifier = self::getInstanceIdentifier();
        $this->instancePath = self::getInstancePath();
        putenv('TYPO3_PATH_ROOT=' . $this->instancePath);
        putenv('TYPO3_PATH_APP=' . $this->instancePath);

        $testbase = new Testbase();
        $testbase->defineTypo3ModeBe();
        $testbase->setTypo3TestingContext();

        $isFirstTest = false;
        $currentTestCaseClass = get_called_class();
        if (self::$currestTestCaseClass !== $currentTestCaseClass) {
            $isFirstTest = true;
            self::$currestTestCaseClass = $currentTestCaseClass;
        }

        if (!$isFirstTest) {
            // Reusing an existing instance. This typically happens for the second, third, ... test
            // in a test case, so environment is set up only once per test case.
            GeneralUtility::purgeInstances();
            $testbase->setUpBasicTypo3Bootstrap($this->instancePath);
            $testbase->initializeTestDatabaseAndTruncateTables();
            Bootstrap::initializeBackendRouter();
            $testbase->loadExtensionTables();
        } else {
            $testbase->removeOldInstanceIfExists($this->instancePath);
            // Basic instance directory structure
            $testbase->createDirectory($this->instancePath . '/fileadmin');
            $testbase->createDirectory($this->instancePath . '/typo3temp/var/transient');
            $testbase->createDirectory($this->instancePath . '/typo3temp/assets');
            $testbase->createDirectory($this->instancePath . '/typo3conf/ext');
            // Additionally requested directories
            foreach ($this->additionalFoldersToCreate as $directory) {
                $testbase->createDirectory($this->instancePath . '/' . $directory);
            }
            $testbase->setUpInstanceCoreLinks($this->instancePath);
            $testbase->linkTestExtensionsToInstance($this->instancePath, $this->testExtensionsToLoad);
            $testbase->linkFrameworkExtensionsToInstance($this->instancePath, $this->frameworkExtensionsToLoad);
            $testbase->linkPathsInTestInstance($this->instancePath, $this->pathsToLinkInTestInstance);
            $testbase->providePathsInTestInstance($this->instancePath, $this->pathsToProvideInTestInstance);
            $localConfiguration['DB'] = $testbase->getOriginalDatabaseSettingsFromEnvironmentOrLocalConfiguration();

            $originalDatabaseName = '';
            $dbPath = '';
            $dbName = '';
            $dbDriver = $localConfiguration['DB']['Connections']['Default']['driver'];
            if ($dbDriver !== 'pdo_sqlite') {
                $originalDatabaseName = $localConfiguration['DB']['Connections']['Default']['dbname'];
                // Append the unique identifier to the base database name to end up with a single database per test case
                $dbName = $originalDatabaseName . '_ft' . $this->identifier;
                $localConfiguration['DB']['Connections']['Default']['dbname'] = $dbName;
                $localConfiguration['DB']['Connections']['Default']['wrapperClass'] = DatabaseConnectionWrapper::class;
                $testbase->testDatabaseNameIsNotTooLong($originalDatabaseName, $localConfiguration);
                if ($dbDriver === 'mysqli') {
                    $localConfiguration['DB']['Connections']['Default']['initCommands'] = 'SET SESSION sql_mode = \'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION,NO_ZERO_DATE,NO_ZERO_IN_DATE,ONLY_FULL_GROUP_BY\';';
                }
            } else {
                $dbPath = $this->instancePath . '/test.sqlite';
                $localConfiguration['DB']['Connections']['Default']['path'] = $dbPath;
            }

                // Set some hard coded base settings for the instance. Those could be overruled by
            // $this->configurationToUseInTestInstance if needed again.
            $localConfiguration['SYS']['displayErrors'] = '1';
            $localConfiguration['SYS']['debugExceptionHandler'] = '';
            $localConfiguration['SYS']['trustedHostsPattern'] = '.*';
            $localConfiguration['SYS']['encryptionKey'] = 'i-am-not-a-secure-encryption-key';
            $localConfiguration['SYS']['caching']['cacheConfigurations']['extbase_object']['backend'] = NullBackend::class;
            $testbase->setUpLocalConfiguration($this->instancePath, $localConfiguration, $this->configurationToUseInTestInstance);
            $defaultCoreExtensionsToLoad = [
                'core',
                'backend',
                'frontend',
                'extbase',
                'install',
                'recordlist',
            ];
            $testbase->setUpPackageStates(
                $this->instancePath,
                $defaultCoreExtensionsToLoad,
                $this->coreExtensionsToLoad,
                $this->testExtensionsToLoad,
                $this->frameworkExtensionsToLoad
            );
            $testbase->setUpBasicTypo3Bootstrap($this->instancePath);
            if ($dbDriver !== 'pdo_sqlite') {
                $testbase->setUpTestDatabase($dbName, $originalDatabaseName);
            } else {
                $testbase->setUpTestDatabase($dbPath, $originalDatabaseName);
            }
            Bootstrap::initializeBackendRouter();
            $testbase->loadExtensionTables();
            $testbase->createDatabaseStructure();
        }
    }

    /**
     * Get DatabaseConnection instance - $GLOBALS['TYPO3_DB']
     *
     * This method should be used instead of direct access to
     * $GLOBALS['TYPO3_DB'] for easy IDE auto completion.
     *
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     * @deprecated since TYPO3 v8, will be removed in TYPO3 v9
     */
    protected function getDatabaseConnection()
    {
        GeneralUtility::logDeprecatedFunction();
        return $GLOBALS['TYPO3_DB'];
    }

    /**
     * @return ConnectionPool
     */
    protected function getConnectionPool()
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * Initialize backend user
     *
     * @param int $userUid uid of the user we want to initialize. This user must exist in the fixture file
     * @return BackendUserAuthentication
     * @throws Exception
     */
    protected function setUpBackendUserFromFixture($userUid)
    {
        $this->importDataSet($this->backendUserFixture);

        $queryBuilder = $this->getConnectionPool()
            ->getQueryBuilderForTable('be_users');
        $queryBuilder->getRestrictions()->removeAll();

        $userRow = $queryBuilder->select('*')
            ->from('be_users')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($userUid, \PDO::PARAM_INT)))
            ->execute()
            ->fetch();

        /** @var $backendUser BackendUserAuthentication */
        $backendUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $sessionId = $backendUser->createSessionId();
        $_COOKIE['be_typo_user'] = $sessionId;
        $backendUser->id = $sessionId;
        $backendUser->sendNoCacheHeaders = false;
        $backendUser->dontSetCookie = true;
        $backendUser->createUserSession($userRow);

        $GLOBALS['BE_USER'] = $backendUser;
        $GLOBALS['BE_USER']->start();
        if (!is_array($GLOBALS['BE_USER']->user) || !$GLOBALS['BE_USER']->user['uid']) {
            throw new Exception(
                'Can not initialize backend user',
                1377095807
            );
        }
        $GLOBALS['BE_USER']->backendCheckLogin();

        return $backendUser;
    }

    /**
     * Imports a data set represented as XML into the test database,
     *
     * @param string $path Absolute path to the XML file containing the data set to load
     * @return void
     * @throws Exception
     */
    protected function importDataSet($path)
    {
        $testbase = new Testbase();
        $testbase->importXmlDatabaseFixture($path);
    }

    /**
     * Import data from a CSV file to database
     * Single file can contain data from multiple tables
     *
     * @param string $path absolute path to the CSV file containing the data set to load
     */
    public function importCSVDataSet($path)
    {
        $dataSet = DataSet::read($path, true);

        foreach ($dataSet->getTableNames() as $tableName) {
            $connection = $this->getConnectionPool()->getConnectionForTable($tableName);
            foreach ($dataSet->getElements($tableName) as $element) {
                try {
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
                    foreach ($element as $columnName => $columnValue) {
                        $types[] = $tableDetails->getColumn($columnName)->getType()->getBindingType();
                    }

                    // Insert the row
                    $connection->insert($tableName, $element, $types);

                    if ($sqlServerIdentityDisabled) {
                        // Reset identity if it has been changed
                        $connection->exec('SET IDENTITY_INSERT ' . $tableName . ' OFF');
                    }
                } catch (DBALException $e) {
                    $this->fail('SQL Error for table "' . $tableName . '": ' . LF . $e->getMessage());
                }
            }
            Testbase::resetTableSequences($connection, $tableName);
        }
    }

    /**
     * Compare data in database with CSV file
     *
     * @param string $path absolute path to the CSV file
     */
    protected function assertCSVDataSet($path)
    {
        $fileName = GeneralUtility::getFileAbsFileName($path);

        $dataSet = DataSet::read($fileName);
        $failMessages = [];

        foreach ($dataSet->getTableNames() as $tableName) {
            $hasUidField = ($dataSet->getIdIndex($tableName) !== null);
            $records = $this->getAllRecords($tableName, $hasUidField);
            foreach ($dataSet->getElements($tableName) as $assertion) {
                $result = $this->assertInRecords($assertion, $records);
                if ($result === false) {
                    if ($hasUidField && empty($records[$assertion['uid']])) {
                        $failMessages[] = 'Record "' . $tableName . ':' . $assertion['uid'] . '" not found in database';
                        continue;
                    }
                    $recordIdentifier = $tableName . ($hasUidField ? ':' . $assertion['uid'] : '');
                    $additionalInformation = ($hasUidField ? $this->renderRecords($assertion, $records[$assertion['uid']]) : $this->arrayToString($assertion));
                    $failMessages[] = 'Assertion in data-set failed for "' . $recordIdentifier . '":' . LF . $additionalInformation;
                    // Unset failed asserted record
                    if ($hasUidField) {
                        unset($records[$assertion['uid']]);
                    }
                } else {
                    // Unset asserted record
                    unset($records[$result]);
                    // Increase assertion counter
                    $this->assertTrue($result !== false);
                }
            }
            if (!empty($records)) {
                foreach ($records as $record) {
                    $recordIdentifier = $tableName . ':' . $record['uid'];
                    $emptyAssertion = array_fill_keys($dataSet->getFields($tableName), '[none]');
                    $reducedRecord = array_intersect_key($record, $emptyAssertion);
                    $additionalInformation = ($hasUidField ? $this->renderRecords($emptyAssertion, $reducedRecord) : $this->arrayToString($reducedRecord));
                    $failMessages[] = 'Not asserted record found for "' . $recordIdentifier . '":' . LF . $additionalInformation;
                }
            }
        }

        if (!empty($failMessages)) {
            $this->fail(implode(LF, $failMessages));
        }
    }

    /**
     * Check if $expectedRecord is present in $actualRecords array
     * and compares if all column values from matches
     *
     * @param array $expectedRecord
     * @param array $actualRecords
     * @return bool|int|string false if record is not found or some column value doesn't match
     */
    protected function assertInRecords(array $expectedRecord, array $actualRecords)
    {
        foreach ($actualRecords as $index => $record) {
            $differentFields = $this->getDifferentFields($expectedRecord, $record);

            if (empty($differentFields)) {
                return $index;
            }
        }

        return false;
    }

    /**
     * Fetches all records from a database table
     * Helper method for assertCSVDataSet
     *
     * @param string $tableName
     * @param bool $hasUidField
     * @return array
     */
    protected function getAllRecords($tableName, $hasUidField = false)
    {
        $queryBuilder = $this->getConnectionPool()
            ->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();
        $statement = $queryBuilder
            ->select('*')
            ->from($tableName)
            ->execute();

        if (!$hasUidField) {
            return $statement->fetchAll();
        }

        $allRecords = [];
        while ($record = $statement->fetch()) {
            $index = $record['uid'];
            $allRecords[$index] = $record;
        }

        return $allRecords;
    }

    /**
     * Format array as human readable string. Used to format verbose error messages in assertCSVDataSet
     *
     * @param array $array
     * @return string
     */
    protected function arrayToString(array $array)
    {
        $elements = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = $this->arrayToString($value);
            }
            $elements[] = "'" . $key . "' => '" . $value . "'";
        }
        return 'array(' . PHP_EOL . '   ' . implode(', ' . PHP_EOL . '   ', $elements) . PHP_EOL . ')' . PHP_EOL;
    }

    /**
     * Format output showing difference between expected and actual db row in a human readable way
     * Used to format verbose error messages in assertCSVDataSet
     *
     * @param array $assertion
     * @param array $record
     * @return string
     */
    protected function renderRecords(array $assertion, array $record)
    {
        $differentFields = $this->getDifferentFields($assertion, $record);
        $columns = [
            'fields' => ['Fields'],
            'assertion' => ['Assertion'],
            'record' => ['Record'],
        ];
        $lines = [];
        $linesFromXmlValues = [];
        $result = '';

        foreach ($differentFields as $differentField) {
            $columns['fields'][] = $differentField;
            $columns['assertion'][] = ($assertion[$differentField] === null ? 'NULL' : $assertion[$differentField]);
            $columns['record'][] = ($record[$differentField] === null ? 'NULL' : $record[$differentField]);
        }

        foreach ($columns as $columnIndex => $column) {
            $columnLength = null;
            foreach ($column as $value) {
                if (strpos($value, '<?xml') === 0) {
                    $value = '[see diff]';
                }
                $valueLength = strlen($value);
                if (empty($columnLength) || $valueLength > $columnLength) {
                    $columnLength = $valueLength;
                }
            }
            foreach ($column as $valueIndex => $value) {
                if (strpos($value, '<?xml') === 0) {
                    if ($columnIndex === 'assertion') {
                        try {
                            $this->assertXmlStringEqualsXmlString((string)$value, (string)$record[$columns['fields'][$valueIndex]]);
                        } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
                            $linesFromXmlValues[] = 'Diff for field "' . $columns['fields'][$valueIndex] . '":' . PHP_EOL . $e->getComparisonFailure()->getDiff();
                        }
                    }
                    $value = '[see diff]';
                }
                $lines[$valueIndex][$columnIndex] = str_pad($value, $columnLength, ' ');
            }
        }

        foreach ($lines as $line) {
            $result .= implode('|', $line) . PHP_EOL;
        }

        foreach ($linesFromXmlValues as $lineFromXmlValues) {
            $result .= PHP_EOL . $lineFromXmlValues . PHP_EOL;
        }

        return $result;
    }

    /**
     * Compares two arrays containing db rows and returns array containing column names which don't match
     * It's a helper method used in assertCSVDataSet
     *
     * @param array $assertion
     * @param array $record
     * @return array
     */
    protected function getDifferentFields(array $assertion, array $record)
    {
        $differentFields = [];

        foreach ($assertion as $field => $value) {
            if (strpos($value, '\\*') === 0) {
                continue;
            } elseif (strpos($value, '<?xml') === 0) {
                try {
                    $this->assertXmlStringEqualsXmlString((string)$value, (string)$record[$field]);
                } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
                    $differentFields[] = $field;
                }
            } elseif ($value === null && $record[$field] !== $value) {
                $differentFields[] = $field;
            } elseif ((string)$record[$field] !== (string)$value) {
                $differentFields[] = $field;
            }
        }

        return $differentFields;
    }

    /**
     * Sets up a root-page containing TypoScript settings for frontend testing.
     *
     * Parameter `$typoScriptFiles` can either be
     * + `[
     *      'path/first.typoscript',
     *      'path/second.typoscript'
     *    ]`
     *   which just loads files to the setup setion of the TypoScript template
     *   record (legacy behavior of this method)
     * + `[
     *      'constants' => ['path/constants.typoscript'],
     *      'setup' => ['path/setup.typoscript']
     *    ]`
     *   which allows to define contents for the `contants` and `setup` part
     *   of the TypoScript template record at the same time
     *
     * @param int $pageId
     * @param array $typoScriptFiles
     */
    protected function setUpFrontendRootPage($pageId, array $typoScriptFiles = [], array $templateValues = [])
    {
        $pageId = (int)$pageId;

        $connection = $this->getConnectionPool()
            ->getConnectionForTable('pages');
        $page = $connection->select(['*'], 'pages', ['uid' => $pageId])->fetch();

        if (empty($page)) {
            $this->fail('Cannot set up frontend root page "' . $pageId . '"');
        }

        // migrate legacy definition to support `constants` and `setup`
        if (!empty($typoScriptFiles)
            && empty($typoScriptFiles['constants'])
            && empty($typoScriptFiles['setup'])
        ) {
            $typoScriptFiles = ['setup' => $typoScriptFiles];
        }

        $databasePlatform = 'mysql';
        if ($connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
            $databasePlatform = 'postgresql';
        }

        $connection->update(
            'pages',
            ['is_siteroot' => 1],
            ['uid' => $pageId]
        );

        $templateFields = array_merge(
            [
                'title' => '',
                'sitetitle' => '',
                'constants' => '',
                'config' => '',
            ],
            $templateValues,
            [
                'pid' => $pageId,
                'clear' => 3,
                'root' => 1,
            ]
        );

        foreach ($typoScriptFiles['constants'] ?? [] as $typoScriptFile) {
            $templateFields['constants'] .= '<INCLUDE_TYPOSCRIPT: source="FILE:' . $typoScriptFile . '">' . LF;
        }
        $templateFields['constants'] .= 'databasePlatform = ' . $databasePlatform . LF;
        foreach ($typoScriptFiles['setup'] ?? [] as $typoScriptFile) {
            $templateFields['config'] .= '<INCLUDE_TYPOSCRIPT: source="FILE:' . $typoScriptFile . '">' . LF;
        }

        $connection = $this->getConnectionPool()
            ->getConnectionForTable('sys_template');
        $connection->delete('sys_template', ['pid' => $pageId]);
        $connection->insert(
            'sys_template',
            $templateFields
        );
    }

    /**
     * Adds TypoScript setup snippet to the existing template record
     *
     * @param int $pageId
     * @param string $typoScript
     */
    protected function addTypoScriptToTemplateRecord(int $pageId, $typoScript)
    {
        $connection = $this->getConnectionPool()
            ->getConnectionForTable('sys_template');

        $template = $connection->select(['*'], 'sys_template', ['pid' => $pageId, 'root' => 1])->fetch();
        if (empty($template)) {
            $this->fail('Cannot find root template on page with id: "' . $pageId . '"');
        }
        $updateFields['config'] = $template['config'] . LF . $typoScript;
        $connection->update(
            'sys_template',
            $updateFields,
            ['uid' => $template['uid']]
        );
    }

    /**
     * @param InternalRequest $request
     * @param InternalRequestContext|null $context
     * @param bool $followRedirects Whether to follow HTTP location redirects
     * @return InternalResponse
     */
    protected function executeFrontendRequest(
        InternalRequest $request,
        InternalRequestContext $context = null,
        bool $followRedirects = false
    ): InternalResponse {
        if ($context === null) {
            $context = new InternalRequestContext();
        }

        $locationHeaders = [];

        do {
            $result = $this->retrieveFrontendRequestResult($request, $context);
            $response = $this->reconstituteFrontendRequestResult($result);
            $locationHeader = $response->getHeaderLine('location');
            if (in_array($locationHeader, $locationHeaders, true)) {
                $this->fail(
                    implode(LF . '* ', array_merge(
                        ['Redirect loop detected:'],
                        $locationHeaders,
                        [$locationHeader]
                    ))
                );
            }
            $locationHeaders[] = $locationHeader;
            $request = new InternalRequest($locationHeader);
        } while ($followRedirects && !empty($locationHeader));

        return $response;
    }

    /**
     * @param InternalRequest $request
     * @param InternalRequestContext $context
     * @param bool $legacyMode
     * @return array
     */
    protected function retrieveFrontendRequestResult(InternalRequest $request, InternalRequestContext $context, bool $withJsonResponse = true): array
    {
        $arguments = [
            'withJsonResponse' => $withJsonResponse,
            'documentRoot' => $this->instancePath,
            'request' => json_encode($request),
            'context' => json_encode($context),
        ];

        $vendorPath = (new Testbase())->getPackagesPath();
        $template = new \Text_Template($vendorPath . '/typo3/testing-framework/Resources/Core/Functional/Fixtures/Frontend/request.tpl');
        $template->setVar(
            [
                'arguments' => var_export($arguments, true),
                'documentRoot' => $this->instancePath,
                'originalRoot' => ORIGINAL_ROOT,
                'vendorPath' => $vendorPath . '/'
            ]
        );

        $php = AbstractPhpProcess::factory();
        $result = $php->runJob($template->render());
        return $result;
    }

    /**
     * @param array $result
     */
    protected function reconstituteFrontendRequestResult(array $result): InternalResponse
    {
        if (!empty($result['stderr'])) {
            $this->fail('Frontend Response is erroneous: ' . LF . $result['stderr']);
        }

        $data = json_decode($result['stdout'], true);

        if ($data === null) {
            $this->fail('Frontend Response is empty: ' . LF . $result['stdout']);
        }

        if ($data['status'] === Response::STATUS_Failure) {
            try {
                $exception = new $data['exception']['type'](
                    $data['exception']['message'],
                    $data['exception']['code']
                );
            } catch (\Throwable $throwable) {
                $exception = new InternalResponseException(
                    (string)$data['exception']['message'],
                    (int)$data['exception']['code'],
                    (string)$data['exception']['type']
                );
            }
            throw $exception;
        }

        return InternalResponse::fromArray($data['content']);
    }

    /**
     * @param int $pageId
     * @param int $languageId
     * @param int $backendUserId
     * @param int $workspaceId
     * @param bool $failOnFailure
     * @param int $frontendUserId
     * @return Response
     * @deprecated Use retrieveFrontendRequestResult() instead
     */
    protected function getFrontendResponse($pageId, $languageId = 0, $backendUserId = 0, $workspaceId = 0, $failOnFailure = true, $frontendUserId = 0)
    {
        $result = $this->getFrontendResult(
            $pageId,
            $languageId,
            $backendUserId,
            $workspaceId,
            $frontendUserId
        );
        if (!empty($result['stderr'])) {
            $this->fail('Frontend Response is erroneous: ' . LF . $result['stderr']);
        }

        $data = json_decode($result['stdout'], true);

        if ($data === null) {
            $this->fail('Frontend Response is empty: ' . LF . $result['stdout']);
        }

        if ($failOnFailure && $data['status'] === Response::STATUS_Failure) {
            $this->fail('Frontend Response has failure:' . LF . $data['error']);
        }

        $response = new Response($data['status'], $data['content'], $data['error']);
        return $response;
    }

    /**
     * Retrieves raw HTTP result by simulation a PHP frontend request
     * using an internal PHP sub process.
     *
     * @param int $pageId
     * @param int $languageId
     * @param int $backendUserId
     * @param int $workspaceId
     * @param int $frontendUserId
     * @return array containing keys 'stdout' and 'stderr'
     * @deprecated Use retrieveFrontendRequestResult() instead
     */
    protected function getFrontendResult($pageId, $languageId = 0, $backendUserId = 0, $workspaceId = 0, $frontendUserId = 0)
    {
        return $this->retrieveFrontendRequestResult(
            (new InternalRequest())
                ->withPageId($pageId)
                ->withLanguageId($languageId),
            (new InternalRequestContext())
                ->withBackendUserId($backendUserId)
                ->withWorkspaceId($workspaceId)
                ->withFrontendUserId($frontendUserId),
            false
        );
    }

    /**
     * Whether to allow modification of IDENTITY_INSERT for SQL Server platform.
     * + null: unspecified, decided later during runtime (based on 'uid' & $TCA)
     * + true: always allow, e.g. before actually importing data
     * + false: always deny, e.g. when importing data is finished
     *
     * @param bool|null $allowIdentityInsert
     * @param bool|null $allowIdentityInsert
     * @throws DBALException
     */
    protected function allowIdentityInsert(?bool $allowIdentityInsert)
    {
        $connection = $this->getConnectionPool()->getConnectionByName(
            ConnectionPool::DEFAULT_CONNECTION_NAME
        );

        if (!$connection instanceof DatabaseConnectionWrapper) {
            return;
        }

        $connection->allowIdentityInsert($allowIdentityInsert);
    }

    /**
     * Invokes database snapshot and either restores data from existing
     * snapshot or otherwise invokes $callback and creates a new snapshot.
     *
     * @param callable $callback
     * @throws DBALException
     */
    protected function withDatabaseSnapshot(callable $callback)
    {
        $connection = $this->getConnectionPool()->getConnectionByName(
            ConnectionPool::DEFAULT_CONNECTION_NAME
        );
        $accessor = new DatabaseAccessor($connection);
        $snapshot = DatabaseSnapshot::instance();

        if ($snapshot->exists()) {
            $snapshot->restore($accessor);
        } else {
            call_user_func($callback);
            $snapshot->create($accessor);
        }
    }

    /**
     * Initializes database snapshot and storage.
     */
    protected static function initializeDatabaseSnapshot()
    {
        $snapshot = DatabaseSnapshot::initialize(
            static::getInstancePath() . '/typo3temp/var/snapshots/',
            static::getInstanceIdentifier()
        );
        if ($snapshot->exists()) {
            $snapshot->purge();
        }
    }

    /**
     * Destroys database snapshot (if available).
     */
    protected static function destroyDatabaseSnapshot()
    {
        DatabaseSnapshot::destroy();
    }

    /**
     * Uses a 7 char long hash of class name as identifier.
     *
     * @return string
     */
    protected static function getInstanceIdentifier(): string
    {
        return substr(sha1(static::class), 0, 7);
    }

    /**
     * @return string
     */
    protected static function getInstancePath(): string
    {
        $identifier = self::getInstanceIdentifier();
        return ORIGINAL_ROOT . 'typo3temp/var/tests/functional-' . $identifier;
    }
}
