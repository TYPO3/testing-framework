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
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\Backend\NullBackend;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\BaseTestCase;
use TYPO3\TestingFramework\Core\Exception;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\DataSet;
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
    const DATABASE_PLATFORM_MYSQL = 'MySQL';
    const DATABASE_PLATFORM_PDO = 'PDO';

    /**
     * Path to a XML fixture dependent on the current database.
     * @var string
     */
    protected $fixturePath = '';

    /**
     * @var string
     */
    protected $databasePlatform;

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
     * @var array
     */
    protected $testExtensionsToLoad = [];

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
     * array(
     *   'typo3/sysext/impext/Tests/Functional/Fixtures/Folders/fileadmin/user_upload' =>
     *   'fileadmin/user_upload',
     *   'typo3conf/ext/my_own_ext/Tests/Functional/Fixtures/Folders/uploads/tx_myownext' =>
     *   'uploads/tx_myownext'
     * );
     *
     * To be able to link from my_own_ext the extension path needs also to be registered in
     * property $testExtensionsToLoad
     *
     * @var array
     */
    protected $pathsToLinkInTestInstance = [];

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
     * /uploads
     *
     * To create additional folders add the paths to this array. Given paths are expected to be
     * relative to the test instance root and have to begin with a slash. Example:
     *
     * array(
     *   'fileadmin/user_upload'
     * );
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
     * Set up creates a test instance and database.
     *
     * This method should be called with parent::setUp() in your test cases!
     *
     * @return void
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function setUp()
    {
        if (!defined('ORIGINAL_ROOT')) {
            $this->markTestSkipped('Functional tests must be called through phpunit on CLI');
        }

        // Use a 7 char long hash of class name as identifier
        $this->identifier = substr(sha1(get_class($this)), 0, 7);
        $this->instancePath = ORIGINAL_ROOT . 'typo3temp/var/tests/functional-' . $this->identifier;

        $testbase = new Testbase();
        $testbase->defineTypo3ModeBe();
        $testbase->definePackagesPath();
        $testbase->setTypo3TestingContext();
        if ($testbase->recentTestInstanceExists($this->instancePath)) {
            // Reusing an existing instance. This typically happens for the second, third, ... test
            // in a test case, so environment is set up only once per test case.
            $testbase->setUpBasicTypo3Bootstrap($this->instancePath);
            $testbase->initializeTestDatabaseAndTruncateTables();
            $testbase->loadExtensionTables();
        } else {
            $testbase->removeOldInstanceIfExists($this->instancePath);
            // Basic instance directory structure
            $testbase->createDirectory($this->instancePath . '/fileadmin');
            $testbase->createDirectory($this->instancePath . '/typo3temp/var/transient');
            $testbase->createDirectory($this->instancePath . '/typo3temp/assets');
            $testbase->createDirectory($this->instancePath . '/typo3conf/ext');
            $testbase->createDirectory($this->instancePath . '/uploads');
            // Additionally requested directories
            foreach ($this->additionalFoldersToCreate as $directory) {
                $testbase->createDirectory($this->instancePath . '/' . $directory);
            }
            $testbase->createLastRunTextfile($this->instancePath);
            $testbase->setUpInstanceCoreLinks($this->instancePath);
            $testbase->linkTestExtensionsToInstance($this->instancePath, $this->testExtensionsToLoad);
            $testbase->linkPathsInTestInstance($this->instancePath, $this->pathsToLinkInTestInstance);
            $localConfiguration['DB'] = $testbase->getOriginalDatabaseSettingsFromEnvironmentOrLocalConfiguration();
            $originalDatabaseName = $localConfiguration['DB']['Connections']['Default']['dbname'];
            // Append the unique identifier to the base database name to end up with a single database per test case
            $localConfiguration['DB']['Connections']['Default']['dbname'] = $originalDatabaseName . '_ft' . $this->identifier;
            $testbase->testDatabaseNameIsNotTooLong($originalDatabaseName, $localConfiguration);
            // Set some hard coded base settings for the instance. Those could be overruled by
            // $this->configurationToUseInTestInstance if needed again.
            $localConfiguration['SYS']['isInitialInstallationInProgress'] = false;
            $localConfiguration['SYS']['isInitialDatabaseImportDone'] = true;
            $localConfiguration['SYS']['displayErrors'] = '1';
            $localConfiguration['SYS']['debugExceptionHandler'] = '';
            $localConfiguration['SYS']['trustedHostsPattern'] = '.*';
            // @todo: This should be moved over to DB/Connections/Default/initCommands
            $localConfiguration['SYS']['setDBinit'] = 'SET SESSION sql_mode = \'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION,NO_ZERO_DATE,NO_ZERO_IN_DATE,ONLY_FULL_GROUP_BY\';';
            $localConfiguration['SYS']['caching']['cacheConfigurations']['extbase_object']['backend'] = NullBackend::class;
            $testbase->setUpLocalConfiguration($this->instancePath, $localConfiguration, $this->configurationToUseInTestInstance);
            $defaultCoreExtensionsToLoad = [
                'core',
                'backend',
                'frontend',
                'lang',
                'extbase',
                'install',
            ];
            $testbase->setUpPackageStates($this->instancePath, $defaultCoreExtensionsToLoad, $this->coreExtensionsToLoad, $this->testExtensionsToLoad);
            $testbase->setUpBasicTypo3Bootstrap($this->instancePath);
            $testbase->setUpTestDatabase($localConfiguration['DB']['Connections']['Default']['dbname'], $originalDatabaseName);
            $testbase->loadExtensionTables();
            $testbase->createDatabaseStructure();
        }

        $databasePlatform = $this->getConnectionPool()
            ->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME)
            ->getDatabasePlatform();

        if ($databasePlatform instanceof MySqlPlatform) {
            $this->setDatabasePlatform(static::DATABASE_PLATFORM_MYSQL);
        } else {
            $this->setDatabasePlatform(static::DATABASE_PLATFORM_PDO);
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

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_users');
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
                    $connection->insert($tableName, $element);
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
                        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
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
                } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
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
     * @param int $pageId
     * @param array $typoScriptFiles
     */
    protected function setUpFrontendRootPage($pageId, array $typoScriptFiles = [])
    {
        $pageId = (int)$pageId;

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
        $page = $connection->select(['*'], 'pages', ['uid' => $pageId])->fetch();

        if (empty($page)) {
            $this->fail('Cannot set up frontend root page "' . $pageId . '"');
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

        $templateFields = [
            'pid' => $pageId,
            'title' => '',
            'constants' => 'databasePlatform = ' . $databasePlatform . LF,
            'config' => '',
            'clear' => 3,
            'root' => 1,
        ];

        foreach ($typoScriptFiles as $typoScriptFile) {
            $templateFields['config'] .= '<INCLUDE_TYPOSCRIPT: source="FILE:' . $typoScriptFile . '">' . LF;
        }
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_template');
        $connection->delete('sys_template', ['pid' => $pageId]);
        $connection->insert(
            'sys_template',
            $templateFields
        );
    }

    /**
     * @param int $pageId
     * @param int $languageId
     * @param int $backendUserId
     * @param int $workspaceId
     * @param bool $failOnFailure
     * @param int $frontendUserId
     * @return Response
     */
    protected function getFrontendResponse($pageId, $languageId = 0, $backendUserId = 0, $workspaceId = 0, $failOnFailure = true, $frontendUserId = 0)
    {
        $pageId = (int)$pageId;
        $languageId = (int)$languageId;

        $additionalParameter = '';

        if (!empty($frontendUserId)) {
            $additionalParameter .= '&frontendUserId=' . (int)$frontendUserId;
        }
        if (!empty($backendUserId)) {
            $additionalParameter .= '&backendUserId=' . (int)$backendUserId;
        }
        if (!empty($workspaceId)) {
            $additionalParameter .= '&workspaceId=' . (int)$workspaceId;
        }

        $arguments = [
            'documentRoot' => $this->instancePath,
            'requestUrl' => 'http://localhost/?id=' . $pageId . '&L=' . $languageId . $additionalParameter,
        ];

        $template = new \Text_Template(ORIGINAL_ROOT . 'typo3/sysext/core/Tests/Functional/Fixtures/Frontend/request.tpl');
        $template->setVar(
            [
                'arguments' => var_export($arguments, true),
                'originalRoot' => ORIGINAL_ROOT,
                'vendorPath' => TYPO3_PATH_PACKAGES
            ]
        );

        $php = \PHPUnit_Util_PHP::factory();
        $response = $php->runJob($template->render());
        $result = json_decode($response['stdout'], true);

        if ($result === null) {
            $this->fail('Frontend Response is empty');
        }

        if ($failOnFailure && $result['status'] === Response::STATUS_Failure) {
            $this->fail('Frontend Response has failure:' . LF . $result['error']);
        }

        $response = new Response($result['status'], $result['content'], $result['error']);
        return $response;
    }

    /**
     * Return the path to a XML fixture dependent on the current database platform that tests are run against.
     *
     * @param string $fileName
     *
     * @return string
     * @throws \Exception
     */
    protected function getXmlFilePath(string $fileName): string
    {
        $baseDir = $this->fixturePath . $this->databasePlatform . '/';
        $xmlFilePath = $baseDir . $fileName;

        if (!file_exists($xmlFilePath)) {
            throw new \Exception(
                'XML fixture file "' . $xmlFilePath . '" not found for database platform: ' . $this->databasePlatform,
                1487620903
            );
        }

        return $xmlFilePath;
    }

    /**
     * @return string
     */
    public function getDatabasePlatform(): string
    {
        return $this->databasePlatform;
    }

    /**
     * @param string $databasePlatform
     *
     * @return $this
     */
    public function setDatabasePlatform(string $databasePlatform)
    {
        $this->databasePlatform = $databasePlatform;
        return $this;
    }

    /**
     * @return string
     */
    public function getFixturePath(): string
    {
        return $this->fixturePath;
    }

    /**
     * @param string $fixturePath
     *
     * @return $this
     */
    public function setFixturePath(string $fixturePath)
    {
        $this->fixturePath = $fixturePath;
        return $this;
    }
}
