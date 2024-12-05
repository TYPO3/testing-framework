<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Core\Acceptance\Extension;

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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Composer\ComposerPackageManager;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\DataSet;
use TYPO3\TestingFramework\Core\Testbase;

/**
 * This codeception extension creates a full TYPO3 instance within
 * typo3temp. Own acceptance test suites should extend from this class
 * and change the properties. This can be used to not copy the whole
 * bootstrapTypo3Environment() method but reuse it instead.
 */
abstract class BackendEnvironment extends Extension
{
    /**
     * Some settings can be overridden by the same name environment variables, see _initialize()
     */
    protected array $config = [
        // config / environment variables
        'typo3Setup' => true,
        'typo3Cleanup' => true,
        'typo3DatabaseHost' => null,
        'typo3DatabaseUsername' => null,
        'typo3DatabasePassword' => null,
        'typo3DatabasePort' => null,
        'typo3DatabaseSocket' => null,
        'typo3DatabaseDriver' => null,
        'typo3DatabaseCharset' => null,

        /**
         * Additional core extensions to load.
         *
         * To be used in own acceptance test suites.
         *
         * If a test suite needs additional core extensions, for instance as a dependency of
         * an extension that is tested, those core extension names can be noted here and will
         * be loaded.
         *
         * @var array
         */
        'coreExtensionsToLoad' => [],

        /**
         * Array of test/fixture extensions paths that should be loaded for a test.
         *
         * To be used in own acceptance test suites.
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
        'testExtensionsToLoad' => [],

        /**
         * Array of test/fixture folder or file paths that should be linked for a test.
         *
         * To be used in own acceptance test suites.
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
         * );
         *
         * To be able to link from my_own_ext the extension path needs also to be registered in
         * property $testExtensionsToLoad
         *
         * @var array
         */
        'pathsToLinkInTestInstance' => [],

        /**
         * This configuration array is merged with TYPO3_CONF_VARS
         * that are set in default configuration and factory configuration
         *
         * To be used in own acceptance test suites.
         *
         * @var array
         */
        'configurationToUseInTestInstance' => [],

        /**
         * Array of folders that should be created inside the test instance document root.
         *
         * To be used in own acceptance test suites.
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
         * array(
         *   '/fileadmin/user_upload'
         * );
         *
         * @var array
         */
        'additionalFoldersToCreate' => [],

        /**
         * Array of absolute paths to .csv files to be loaded into database.
         * This can be used to prime the database with fixture records.
         *
         * The core for example uses this to have a default page tree and
         * to create valid sessions so users are logged-in automatically.
         *
         * Example: [ __DIR__ . '/../../Fixtures/BackendEnvironment.csv' ]
         */
        'csvDatabaseFixtures' => [],

        /**
         * Copy files within created test instance.
         *
         * @var array<string, string[]>
         */
        'copyInstanceFiles' => [],

        /**
         * Should target paths for self::$config['copyInstanceFiles'] be created.
         * Will throw an exception if folder does not exists and set to `false`.
         */
        'copyInstanceFilesCreateTargetPath' => true,
    ];

    /**
     * This array is to be extended by consuming extensions.
     * It is merged with $config early in bootstrap to create
     * the final setup configuration.
     *
     * Typcially, extensions specify here which core extensions
     * should be loaded and that the extension that is tested should
     * be loaded by setting 'coreExtensionsToLoad' and 'testExtensionsToLoad'.
     *
     * @var array
     */
    protected $localConfig = [];

    /**
     * Events to listen to
     */
    public static $events = [
        Events::SUITE_BEFORE => 'bootstrapTypo3Environment',
        Events::TEST_BEFORE => 'cleanupTypo3Environment',
    ];

    /**
     * @var string|null Test instance path when created.
     */
    protected ?string $instancePath = null;

    /**
     * Initialize config array, called before events.
     *
     * Config options can be overridden via .yml config, example:
     *
     * extensions:
     *   enabled:
     *     - TYPO3\TestingFramework\Core\Acceptance\Extension\CoreEnvironment:
     *       typo3DatabaseHost: 127.0.0.1
     *
     * Some config options can also be set via environment variables, which then
     * take precedence:
     *
     * typo3DatabaseHost=127.0.0.1 ./bin/codecept run ...
     */
    public function _initialize(): void
    {
        $this->config = array_replace($this->config, $this->localConfig);
        $env = getenv('typo3Setup');
        $this->config['typo3Setup'] = is_string($env)
            ? (trim($env) === 'false' ? false : (bool)$env)
            : $this->config['typo3Setup'];
        $env = getenv('typo3Cleanup');
        $this->config['typo3Cleanup'] = is_string($env)
            ? (trim($env) === 'false' ? false : (bool)$env)
            : $this->config['typo3Cleanup'];
        $env = getenv('typo3DatabaseHost');
        $this->config['typo3DatabaseHost'] = is_string($env) ? trim($env) : $this->config['typo3DatabaseHost'];
        $env = getenv('typo3DatabaseUsername');
        $this->config['typo3DatabaseUsername'] = is_string($env) ? trim($env) : $this->config['typo3DatabaseUsername'];
        $env = getenv('typo3DatabasePassword');
        $this->config['typo3DatabasePassword'] = is_string($env) ? $env : $this->config['typo3DatabasePassword'];
        $env = getenv('typo3DatabasePort');
        $this->config['typo3DatabasePort'] = is_string($env) ? (int)$env : (int)$this->config['typo3DatabasePort'];
        $env = getenv('typo3DatabaseSocket');
        $this->config['typo3DatabaseSocket'] = is_string($env) ? trim($env) : $this->config['typo3DatabaseSocket'];
        $env = getenv('typo3DatabaseDriver');
        $this->config['typo3DatabaseDriver'] = is_string($env) ? trim($env) : $this->config['typo3DatabaseDriver'];
        $env = getenv('typo3DatabaseCharset');
        $this->config['typo3DatabaseCharset'] = is_string($env) ? trim($env) : $this->config['typo3DatabaseCharset'];
    }

    /**
     * Handle SUITE_BEFORE event.
     *
     * Create a full standalone TYPO3 instance within typo3temp/var/tests/acceptance,
     * create a database and create database schema.
     *
     * @param SuiteEvent $suiteEvent
     */
    public function bootstrapTypo3Environment(SuiteEvent $suiteEvent)
    {
        if (!$this->config['typo3Setup']) {
            return;
        }
        $testbase = new Testbase();
        $testbase->defineOriginalRootPath();
        $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/tests/acceptance');
        $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/transient');

        $instancePath = $this->instancePath = ORIGINAL_ROOT . 'typo3temp/var/tests/acceptance';
        putenv('TYPO3_PATH_ROOT=' . $instancePath);
        putenv('TYPO3_PATH_APP=' . $instancePath);
        $testbase->setTypo3TestingContext();

        $testbase->removeOldInstanceIfExists($instancePath);
        // Basic instance directory structure
        $testbase->createDirectory($instancePath . '/fileadmin');
        $testbase->createDirectory($instancePath . '/typo3temp/var/transient');
        $testbase->createDirectory($instancePath . '/typo3temp/assets');
        $testbase->createDirectory($instancePath . '/typo3conf/ext');
        // Additionally requested directories
        foreach ($this->config['additionalFoldersToCreate'] as $directory) {
            $testbase->createDirectory($instancePath . '/' . $directory);
        }
        $coreExtensionsToLoad = $this->config['coreExtensionsToLoad'];
        $testbase->setUpInstanceCoreLinks($instancePath, [], $coreExtensionsToLoad);
        $testExtensionsToLoad = $this->config['testExtensionsToLoad'];
        $testbase->linkTestExtensionsToInstance($instancePath, $testExtensionsToLoad);
        $testbase->linkPathsInTestInstance($instancePath, $this->config['pathsToLinkInTestInstance']);
        $localConfiguration['DB'] = $testbase->getOriginalDatabaseSettingsFromEnvironmentOrLocalConfiguration($this->config);
        $dbDriver = $localConfiguration['DB']['Connections']['Default']['driver'];
        $originalDatabaseName = '';
        if ($dbDriver !== 'pdo_sqlite') {
            $this->output->debug('Database Connection: ' . json_encode($localConfiguration['DB']));
            $originalDatabaseName = $localConfiguration['DB']['Connections']['Default']['dbname'];
            // Append the unique identifier to the base database name to end up with a single database per test case
            $localConfiguration['DB']['Connections']['Default']['dbname'] = $originalDatabaseName . '_at';
            $testbase->testDatabaseNameIsNotTooLong($originalDatabaseName, $localConfiguration);
            if ($dbDriver === 'mysqli' || $dbDriver === 'pdo_mysql') {
                $localConfiguration['DB']['Connections']['Default']['charset'] = 'utf8mb4';
                $localConfiguration['DB']['Connections']['Default']['defaultTableOptions']['charset'] = 'utf8mb4';
                $localConfiguration['DB']['Connections']['Default']['defaultTableOptions']['collation'] = 'utf8mb4_unicode_ci';
            }
        } else {
            // sqlite dbs of all tests are stored in a dir parallel to instance roots. Allows defining this path as tmpfs.
            $this->output->debug('Database Connection: ' . json_encode($localConfiguration['DB']));
            $testbase->createDirectory(dirname($instancePath) . '/acceptance-sqlite-dbs');
            $dbPathSqlite = dirname($instancePath) . '/acceptance-sqlite-dbs/test_acceptance.sqlite';
            $localConfiguration['DB']['Connections']['Default']['path'] = $dbPathSqlite;
        }
        // Set some hard coded base settings for the instance. Those could be overruled by
        // $this->config['configurationToUseInTestInstance ']if needed again.
        $localConfiguration['BE']['debug'] = true;
        $localConfiguration['BE']['installToolPassword'] = '$P$notnotnotnotnotnot.validvalidva';
        $localConfiguration['SYS']['displayErrors'] = true;
        $localConfiguration['SYS']['devIPmask'] = '*';
        $localConfiguration['SYS']['exceptionalErrors'] = E_ALL;
        $localConfiguration['SYS']['errorHandlerErrors'] = E_ALL;
        $localConfiguration['SYS']['trustedHostsPattern'] = '.*';
        $localConfiguration['SYS']['encryptionKey'] = 'iAmInvalid';
        // @todo: This sql_mode should be enabled as soon as styleguide and dataHandler can cope with it
        //$localConfiguration['SYS']['setDBinit'] = 'SET SESSION sql_mode = \'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION,NO_ZERO_DATE,NO_ZERO_IN_DATE,ONLY_FULL_GROUP_BY\';';
        $localConfiguration['GFX']['processor'] = 'GraphicsMagick';
        $testbase->setUpLocalConfiguration($instancePath, $localConfiguration, $this->config['configurationToUseInTestInstance']);
        $frameworkExtensionPaths = [];
        $testbase->setUpPackageStates($instancePath, [], $coreExtensionsToLoad, $testExtensionsToLoad, $frameworkExtensionPaths);
        $this->output->debug('Loaded Extensions: ' . json_encode(array_merge($coreExtensionsToLoad, $testExtensionsToLoad)));
        $container = $testbase->setUpBasicTypo3Bootstrap($instancePath);
        if ($dbDriver !== 'pdo_sqlite') {
            $testbase->setUpTestDatabase($localConfiguration['DB']['Connections']['Default']['dbname'], $originalDatabaseName);
        } else {
            $testbase->setUpTestDatabase($localConfiguration['DB']['Connections']['Default']['path'], $originalDatabaseName);
        }
        $testbase->loadExtensionTables();
        $testbase->createDatabaseStructure($container);

        // Unregister core error handler again, which has been initialized by
        // $testbase->setUpBasicTypo3Bootstrap($instancePath); for DB schema
        // migration.
        // @todo: See which other possible state should be dropped here again (singletons, ...?)
        restore_error_handler();

        $this->applyDefaultCopyInstanceFilesConfiguration();
        $copyInstanceFilesCreateTargetPaths = (bool)($this->config['copyInstanceFilesCreateTargetPath'] ?? true);
        $copyInstanceFiles = $this->config['copyInstanceFiles'] ?? [];
        if (is_array($copyInstanceFiles) && $copyInstanceFiles !== []) {
            $testbase->copyInstanceFiles($instancePath, $copyInstanceFiles, $copyInstanceFilesCreateTargetPaths);
        }

        // Unset a closure or phpunit kicks in with a 'serialization of \Closure is not allowed'
        // Alternative solution:
        // unset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['cliKeys']['extbase']);
        $suite = $suiteEvent->getSuite();
        $suite->backupGlobals(false);

        foreach ($this->config['csvDatabaseFixtures'] as $fixture) {
            DataSet::import($fixture);
        }
    }

    /**
     * Method executed after each test
     */
    public function cleanupTypo3Environment()
    {
        if (!$this->config['typo3Cleanup']) {
            return;
        }
        // Reset uc db field of be_user "admin" to null to reduce
        // possible side effects between single tests.
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('be_users')
            ->update('be_users', ['uc' => null], ['uid' => 1]);
    }

    private function applyDefaultCopyInstanceFilesConfiguration(): void
    {
        if ($this->hasExtension('typo3/cms-backend')) {
            ArrayUtility::mergeRecursiveWithOverrule(
                $this->config,
                [
                    'copyInstanceFiles' => [
                        // Create favicon.ico to suppress potential javascript errors in console
                        // which are caused by calling a non html in the browser, e.g. seo sitemap xml
                        'typo3/sysext/backend/Resources/Public/Icons/favicon.ico' => [
                            'favicon.ico',
                        ],
                    ],
                ]
            );
        }
        if ($this->hasExtension('typo3/cms-install')) {
            ArrayUtility::mergeRecursiveWithOverrule(
                $this->config,
                [
                    'copyInstanceFiles' => [
                        // Provide some files into the test instance normally added by installer
                        'typo3/sysext/install/Resources/Private/FolderStructureTemplateFiles/root-htaccess' => [
                            '.htaccess',
                        ],
                        'typo3/sysext/install/Resources/Private/FolderStructureTemplateFiles/resources-root-htaccess' => [
                            'fileadmin/.htaccess',
                        ],
                        'typo3/sysext/install/Resources/Private/FolderStructureTemplateFiles/fileadmin-temp-htaccess' => [
                            'fileadmin/_temp_/.htaccess',
                        ],
                        'typo3/sysext/install/Resources/Private/FolderStructureTemplateFiles/fileadmin-temp-index.html' => [
                            'fileadmin/_temp_/index.html',
                        ],
                        'typo3/sysext/install/Resources/Private/FolderStructureTemplateFiles/typo3temp-var-htaccess' => [
                            'typo3temp/var/.htaccess',
                        ],
                    ],
                ]
            );
        }
    }

    /**
     * Verify if extension is available in the system and within the acceptance test instance.
     */
    protected function hasExtension(string $extensionKeyOrComposerPackageName): bool
    {
        $instanceExtensions = $this->getInstanceExtensionKeys(
            $this->config['coreExtensionsToLoad'],
            $this->config['testExtensionsToLoad'],
        );
        $packageInfo = (new ComposerPackageManager())->getPackageInfo($extensionKeyOrComposerPackageName);
        if ($packageInfo === null) {
            return false;
        }
        return $packageInfo->getExtensionKey() !== '' && in_array($packageInfo->getExtensionKey(), $instanceExtensions, true);
    }

    /**
     * Gather list of extension keys available within created test instance
     * based on `coreExtensionsToLoad` and `testExtensionToLoad` config.
     */
    private function getInstanceExtensionKeys(
        array $coreExtensionsToLoad,
        array $testExtensionsToLoad,
    ): array {
        $composerPackageManager = new ComposerPackageManager();
        if ($coreExtensionsToLoad === []) {
            // Fallback to all system extensions needed for TYPO3 acceptanceInstall tests.
            $coreExtensionsToLoad = $composerPackageManager->getSystemExtensionExtensionKeys();
        }
        $result = [];
        foreach ($coreExtensionsToLoad as $extensionKeyOrComposerPackageName) {
            $packageInfo = $composerPackageManager->getPackageInfo($extensionKeyOrComposerPackageName);
            if ($packageInfo === null || $packageInfo->getExtensionKey() === '') {
                continue;
            }
            $result[] = $packageInfo->getExtensionKey();
        }
        foreach ($testExtensionsToLoad as $extensionKeyOrComposerPackageName) {
            $packageInfo = $composerPackageManager->getPackageInfo($extensionKeyOrComposerPackageName);
            if ($packageInfo === null || $packageInfo->getExtensionKey() === '') {
                continue;
            }
            $result[] = $packageInfo->getExtensionKey();
        }
        return $result;
    }
}
