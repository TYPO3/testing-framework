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

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\MariaDBPlatform as DoctrineMariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform as DoctrineMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform as DoctrinePostgreSQLPlatform;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Schema\SchemaManagerFactory;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\ClassLoadingInformation;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Schema\SchemaMigrator;
use TYPO3\CMS\Core\Database\Schema\SqlReader;
use TYPO3\CMS\Core\Http\Application as CoreHttpApplication;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Composer\ComposerPackageManager;
use TYPO3\TestingFramework\Composer\PackageInfo;

/**
 * This is a helper class used by unit, functional and acceptance test
 * environment builders.
 * It contains methods to create test environments.
 *
 * This class is for internal use only and may change wihtout further notice.
 *
 * Use the classes `UnitTestCase`, `FunctionalTestCase` or `AcceptanceCoreEnvironment`
 * to indirectly benefit from this class in own extensions.
 */
class Testbase
{
    private ComposerPackageManager $composerPackageManager;

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
        $this->composerPackageManager = new ComposerPackageManager();
    }

    /**
     * Sets $_SERVER['SCRIPT_NAME'].
     * For unit tests only
     */
    public function defineSitePath(): void
    {
        // @todo: Remove when dropping support for v12
        $hasConsolidatedHttpEntryPoint = class_exists(CoreHttpApplication::class);
        $scriptPrefix = $hasConsolidatedHttpEntryPoint ? '' : 'typo3/';

        $_SERVER['SCRIPT_NAME'] = $this->getWebRoot() . $scriptPrefix . 'index.php';
        if (!file_exists($_SERVER['SCRIPT_NAME'])) {
            $this->exitWithMessage('Unable to determine path to entry script. Please check your path or set an environment variable \'TYPO3_PATH_ROOT\' to your root path.');
        }
    }

    /**
     * Defines the constant ORIGINAL_ROOT for the path to the original TYPO3 document root.
     * For functional / acceptance tests only
     * If ORIGINAL_ROOT already is defined, this method is a no-op.
     */
    public function defineOriginalRootPath(): void
    {
        if (!defined('ORIGINAL_ROOT')) {
            define('ORIGINAL_ROOT', $this->getWebRoot());
        }

        if (!file_exists(ORIGINAL_ROOT . 'index.php')) {
            $this->exitWithMessage('Unable to determine path to entry script. Please check your path or set an environment variable \'TYPO3_PATH_ROOT\' to your root path.');
        }
    }

    /**
     * Sets the environment variable TYPO3_CONTEXT to testing.
     * Needs to be called after each Functional executing a frontend request
     */
    public function setTypo3TestingContext(): void
    {
        putenv('TYPO3_CONTEXT=Testing');
    }

    /**
     * Creates directories, recursively if required.
     *
     * @param string $directory Absolute path to directories to create
     * @throws Exception
     */
    public function createDirectory($directory): void
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
     * Returns PHP code that, when executed in $from, will return the path to $to
     * Copied from Composer sources and adapted for limited use case here
     *
     * @see https://github.com/composer/composer
     * @throws \InvalidArgumentException
     */
    private function findShortestPathCode(string $from, string $to): string
    {
        if ($from === $to) {
            return '__FILE__';
        }

        $commonPath = $to;
        while (!str_starts_with($from . '/', $commonPath . '/')   && $commonPath !== '/' && preg_match('{^[a-z]:/?$}i', $commonPath) !== false && $commonPath !== '.') {
            $commonPath = str_replace('\\', '/', \dirname($commonPath));
        }

        if ($commonPath === '/' || $commonPath === '.' || !str_starts_with($from, $commonPath)) {
            return var_export($to, true);
        }

        $commonPath = rtrim($commonPath, '/') . '/';
        if (str_starts_with($to, $from . '/')) {
            return '__DIR__ . ' . var_export(substr($to, \strlen($from)), true);
        }
        $sourcePathDepth = substr_count(substr($from, \strlen($commonPath)), '/');
        $commonPathCode = "__DIR__ . '" . str_repeat('/..', $sourcePathDepth) . "'";
        $relTarget = substr($to, \strlen($commonPath));

        return $commonPathCode . ($relTarget !== '' ? ' . ' . var_export('/' . $relTarget, true) : '');
    }

    /**
     * Remove test instance folder structure if it exists.
     * This may happen if a functional test before threw a fatal or is too old
     *
     * @param non-empty-string $instancePath Absolute path to test instance
     * @throws Exception
     */
    public function removeOldInstanceIfExists($instancePath): void
    {
        if (is_dir($instancePath)) {
            if (!str_contains($instancePath, 'typo3temp')) {
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
     * @param non-empty-string $instancePath Absolute path to test instance
     * @param string[] $defaultCoreExtensionsToLoad Default core extensions to load (extension-key or package name)
     * @param string[] $coreExtensionsToLoad Core extension to load for test-case (extension-key or package name)
     * @throws Exception
     */
    public function setUpInstanceCoreLinks(
        string $instancePath,
        array $defaultCoreExtensionsToLoad = [],
        array $coreExtensionsToLoad = []
    ): void {
        $this->createDirectory($instancePath . '/typo3');
        $this->createDirectory($instancePath . '/typo3/sysext');

        $linksToSet = [];
        $coreExtensions = array_unique(array_merge($defaultCoreExtensionsToLoad, $coreExtensionsToLoad));
        // @todo Fallback to all system extensions needed for TYPO3 acceptanceInstall tests.
        if ($coreExtensions === []) {
            $coreExtensions = $this->composerPackageManager->getSystemExtensionExtensionKeys();
        }
        foreach ($coreExtensions as $coreExtension) {
            $packageInfo = $this->composerPackageManager->getPackageInfoWithFallback($coreExtension);
            if (!($packageInfo?->isSystemExtension() ?? false)) {
                continue;
            }
            $linksToSet[$packageInfo->getRealPath() . '/'] = 'typo3/sysext/' . $packageInfo->getExtensionKey();
        }

        chdir($instancePath);
        foreach ($linksToSet as $from => $to) {
            // @todo Need we some "relative" or "short-path" calculation for the symlink to avoid absolute source ?
            $success = @symlink($from, $to);
            if (!$success) {
                throw new Exception(
                    'Creating link failed: from ' . $from . ' to: ' . $to,
                    1376657199
                );
            }
        }

        // We can't just link the entry scripts here, because acceptance tests will make use of them
        // and we need Composer Mode to be turned off, thus they need to be rewritten to use the SystemEnvironmentBuilder
        // of the testing framework.
        // @todo: Remove else branch when dropping support for v12
        $hasConsolidatedHttpEntryPoint = class_exists(CoreHttpApplication::class);
        $installPhpExists = file_exists($instancePath . '/typo3/sysext/install/Resources/Private/Php/install.php');
        if ($hasConsolidatedHttpEntryPoint) {
            $entryPointsToSet = [
                $instancePath . '/typo3/sysext/core/Resources/Private/Php/index.php' => $instancePath . '/index.php',
            ];
            if ($installPhpExists && in_array('install', $coreExtensions, true)) {
                $entryPointsToSet[$instancePath . '/typo3/sysext/install/Resources/Private/Php/install.php'] = $instancePath . '/typo3/install.php';
            }
        } else {
            $entryPointsToSet = [
                $instancePath . '/typo3/sysext/backend/Resources/Private/Php/backend.php' => $instancePath . '/typo3/index.php',
                $instancePath . '/typo3/sysext/frontend/Resources/Private/Php/frontend.php' => $instancePath . '/index.php',
            ];
            if ($installPhpExists) {
                $entryPointsToSet[$instancePath . '/typo3/sysext/install/Resources/Private/Php/install.php'] = $instancePath . '/typo3/install.php';
            }
        }
        $autoloadFile = dirname(__DIR__, 4) . '/autoload.php';

        foreach ($entryPointsToSet as $source => $target) {
            if (($entryPointContent = file_get_contents($source)) === false) {
                throw new \UnexpectedValueException(sprintf('Source file (%s) was not found.', $source), 1636244753);
            }
            $entryPointContent = (string)preg_replace(
                '/__DIR__ \. \'[^\']+\'/',
                $this->findShortestPathCode($target, $autoloadFile),
                $entryPointContent
            );
            $entryPointContent = (string)preg_replace(
                '/\\\\TYPO3\\\\CMS\\\\Core\\\\Core\\\\SystemEnvironmentBuilder::run\(/',
                '\TYPO3\TestingFramework\Core\SystemEnvironmentBuilder::run(',
                $entryPointContent
            );
            if ($entryPointContent === '') {
                throw new \UnexpectedValueException(
                    sprintf('Error while customizing the source file (%s).', $source),
                    1636244910
                );
            }
            file_put_contents($target, $entryPointContent);
        }
    }

    /**
     * Link test extensions to the typo3conf/ext folder of the instance.
     * For functional and acceptance tests.
     *
     * @param non-empty-string $instancePath Absolute path to test instance
     * @param non-empty-string[] $extensionPaths Contains paths to extensions relative to document root
     * @throws Exception
     */
    public function linkTestExtensionsToInstance(string $instancePath, array $extensionPaths): void
    {
        foreach ($extensionPaths as $extensionPath) {
            $packageInfo = $this->composerPackageManager->getPackageInfoWithFallback($extensionPath);
            if ($packageInfo instanceof PackageInfo) {
                $installPath = $packageInfo->getRealPath();
                $destinationPath = $instancePath . '/typo3conf/ext/' . $packageInfo->getExtensionKey();
                // @todo Need we some "relative" or "short-path" calculation for the symlink to avoid absolute source ?
                $success = @symlink($installPath, $destinationPath);
                if (!$success) {
                    throw new Exception(
                        'Can not link extension folder: ' . $installPath . ' to ' . $destinationPath . ' for '
                        . $packageInfo->getName(),
                        1376657142
                    );
                }
                continue;
            }

            // TYPO3 MonoRepo and generic fallback (legacy install structure). This is mainly needed,
            // as TYPO3 MonoRepo does not have required the system extensions in the root composer.json.
            $absoluteExtensionPath = ORIGINAL_ROOT . $extensionPath;
            if (!is_dir($absoluteExtensionPath)) {
                throw new Exception(
                    'Test extension path ' . $absoluteExtensionPath . ' not found',
                    1376745645
                );
            }
            $destinationPath = $instancePath . '/typo3conf/ext/' . basename($absoluteExtensionPath);
            $success = @symlink($absoluteExtensionPath . '/', $destinationPath);
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
     * @param non-empty-string $instancePath Absolute path to test instance
     * @param non-empty-string[] $extensionPaths Contains paths to extensions relative to document root
     * @throws Exception
     */
    public function linkFrameworkExtensionsToInstance(string $instancePath, array $extensionPaths): void
    {
        $testingFrameworkPath = $this->composerPackageManager->getPackageInfo('typo3/testing-framework')->getRealPath();
        foreach ($extensionPaths as $extensionPath) {
            $absoluteExtensionPath = $testingFrameworkPath . '/' . $extensionPath;
            if (!is_dir($absoluteExtensionPath)) {
                throw new Exception(
                    'Framework extension path ' . $absoluteExtensionPath . ' not found',
                    1533626848
                );
            }
            $destinationPath = $instancePath . '/typo3conf/ext/' . basename($absoluteExtensionPath);
            $success = @symlink($absoluteExtensionPath . '/', $destinationPath);
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
     * @param non-empty-string $instancePath Absolute path to test instance
     * @param array<string, non-empty-string> $pathsToLinkInTestInstance Contains paths as array of source => destination in key => value pairs of folders relative to test instance root
     * @throws Exception if a source path could not be found and on failing creating the symlink
     */
    public function linkPathsInTestInstance(string $instancePath, array $pathsToLinkInTestInstance): void
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
            $success = @symlink($sourcePath, $destinationPath);
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
     * @param non-empty-string $instancePath
     * @param array<string, non-empty-string> $pathsToProvideInTestInstance
     * @throws Exception
     */
    public function providePathsInTestInstance(string $instancePath, array $pathsToProvideInTestInstance): void
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
     * environment variables (recommended). The method fetches these.
     *
     * A unique name will be added to the database name later.
     *
     * @param array $config Incoming config arguments, used especially in acceptance test setups
     * @throws Exception
     * @return array [DB][host], [DB][username], ...
     */
    public function getOriginalDatabaseSettingsFromEnvironmentOrLocalConfiguration(array $config = []): array
    {
        $databaseName = mb_strtolower(trim($config['typo3DatabaseName'] ?? (getenv('typo3DatabaseName') ?: '')));
        $databaseHost = trim($config['typo3DatabaseHost'] ?? (getenv('typo3DatabaseHost') ?: ''));
        $databaseUsername = trim($config['typo3DatabaseUsername'] ?? (getenv('typo3DatabaseUsername') ?: ''));
        $databasePassword = $config['typo3DatabasePassword'] ?? (getenv('typo3DatabasePassword') ?: '');
        $databasePasswordTrimmed = trim($databasePassword);
        $databasePort = trim((string)($config['typo3DatabasePort'] ?? (getenv('typo3DatabasePort') ?: '')));
        $databaseSocket = trim($config['typo3DatabaseSocket'] ?? (getenv('typo3DatabaseSocket') ?: ''));
        $databaseDriver = trim($config['typo3DatabaseDriver'] ?? (getenv('typo3DatabaseDriver') ?: ''));
        $databaseCharset = trim($config['typo3DatabaseCharset'] ?? (getenv('typo3DatabaseCharset') ?: ''));
        if ($databaseName || $databaseHost || $databaseUsername || $databasePassword || $databasePort || $databaseSocket || $databaseDriver || $databaseCharset) {
            // Try to get database credentials from environment variables first
            $originalConfigurationArray = [
                'DB' => [
                    'Connections' => [
                        'Default' => [
                            'driver' => 'mysqli',
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
        } else {
            throw new Exception(
                'Database credentials for tests are neither set through environment'
                . ' variables, and can not be found in an existing LocalConfiguration file',
                1397406356
            );
        }

        // Instead of letting code run and retrieving mysterious result and errors, check for dropped sql driver
        // support and throw a corresponding exception with a proper message.
        // @todo Remove this in core v13 compatible testing-framework.
        if (in_array($originalConfigurationArray['DB']['Connections']['Default']['driver'], ['sqlsrv', 'pdo_sqlsrv'], true)) {
            throw new \RuntimeException(
                'Microsoft SQL Server support has been dropped for TYPO3 v12 and testing-framework.'
                . ' Therefore driver "' . $databaseDriver . '" is invalid.',
                1651335681
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
    public function testDatabaseNameIsNotTooLong($originalDatabaseName, array $configuration): void
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
     * @param non-empty-string $instancePath Absolute path to test instance
     * @param array $configuration Base configuration array
     * @param array $overruleConfiguration Overrule factory and base configuration
     * @throws Exception
     */
    public function setUpLocalConfiguration(string $instancePath, array $configuration, array $overruleConfiguration): void
    {
        // Base of final LocalConfiguration is core factory configuration
        $coreExtensionPath = $this->composerPackageManager->getPackageInfo('typo3/cms-core')?->getRealPath() ?? '';
        $finalConfigurationArray = require $coreExtensionPath . '/Configuration/FactoryConfiguration.php';
        $finalConfigurationArray = array_replace_recursive($finalConfigurationArray, $configuration);
        $finalConfigurationArray = array_replace_recursive($finalConfigurationArray, $overruleConfiguration);
        $result = @file_put_contents(
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
     * Compile typo3conf/PackageStates.php or var/build/PackageArtifact.php
     * containing default packages like core, a test specific list of additional core extensions,
     * and a list of test extensions.
     * For functional and acceptance tests.
     *
     * @param non-empty-string $instancePath Absolute path to test instance
     * @param non-empty-string[] $defaultCoreExtensionsToLoad Default list of core extensions to load
     * @param non-empty-string[] $additionalCoreExtensionsToLoad Additional core extensions to load
     * @param non-empty-string[] $testExtensionPaths Paths to test extensions relative to document root
     * @param non-empty-string[] $frameworkExtensionPaths Paths to framework extensions relative to testing framework package
     * @throws Exception
     */
    public function setUpPackageStates(
        string $instancePath,
        array $defaultCoreExtensionsToLoad,
        array $additionalCoreExtensionsToLoad,
        array $testExtensionPaths,
        array $frameworkExtensionPaths
    ): void {
        $packageStates = [
            'packages' => [],
            'version' => 5,
        ];

        // Register default list of extensions and set active
        foreach ($defaultCoreExtensionsToLoad as $extensionName) {
            if ($packageInfo = $this->composerPackageManager->getPackageInfo($extensionName)) {
                $extensionName = $packageInfo->getExtensionKey();
            }
            $packageStates['packages'][$extensionName] = [
                'packagePath' => 'typo3/sysext/' . $extensionName . '/',
            ];
        }

        // Register additional core extensions and set active
        foreach ($additionalCoreExtensionsToLoad as $extensionName) {
            if ($packageInfo = $this->composerPackageManager->getPackageInfo($extensionName)) {
                $extensionName = $packageInfo->getExtensionKey();
            }
            $packageStates['packages'][$extensionName] = [
                'packagePath' => 'typo3/sysext/' . $extensionName . '/',
            ];
        }

        // Activate test extensions that have been symlinked before
        foreach ($testExtensionPaths as $extensionPath) {
            if ($packageInfo = $this->composerPackageManager->getPackageInfo($extensionPath)) {
                $extensionName = $packageInfo->getExtensionKey();
            } else {
                $extensionName = basename($extensionPath);
            }
            $packageStates['packages'][$extensionName] = [
                'packagePath' => 'typo3conf/ext/' . $extensionName . '/',
            ];
        }

        // Activate framework extensions that have been symlinked before
        foreach ($frameworkExtensionPaths as $extensionPath) {
            $extensionName = basename($extensionPath);
            $packageStates['packages'][$extensionName] = [
                'packagePath' => 'typo3conf/ext/' . $extensionName . '/',
            ];
        }

        $dependencyOrderingService = new DependencyOrderingService();
        $packageManager = new PackageManager(
            $dependencyOrderingService,
            $instancePath . '/typo3conf/PackageStates.php',
            $instancePath
        );
        // PackageManager is required to create a Package instance...
        $packageCollection = PackageCollection::fromPackageStates(
            $this->composerPackageManager,
            $packageManager,
            $instancePath,
            $packageStates['packages'],
        );
        $packageStates['packages'] = $packageCollection->sortPackageStates(
            $packageStates['packages'],
            $dependencyOrderingService
        );

        $result = file_put_contents(
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
     */
    public function setUpTestDatabase(string $databaseName, string $originalDatabaseName): void
    {
        // First close existing connections from a possible previous test case and
        // tell our ConnectionPool there are no current connections anymore. In case
        // database does not exist yet, an exception is thrown which we catch here.
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        try {
            $connection = $connectionPool->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
            $connection->close();
        } catch (DBALException) {
        }
        $connectionPool->resetConnections();

        // Drop database if exists. Directly using the Doctrine DriverManager to
        // work around connection caching in ConnectionPool.
        // @todo: This should by now work with using "our" ConnectionPool again, it does now, though.
        $connectionParameters = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default'];
        unset($connectionParameters['dbname']);
        $configuration = (new Configuration())->setSchemaManagerFactory(GeneralUtility::makeInstance(DefaultSchemaManagerFactory::class));
        // @todo Remove class exist check if supported minimal TYPO3 version is raised to v13+.
        if (class_exists(\TYPO3\CMS\Core\Database\Schema\SchemaManager\CoreSchemaManagerFactory::class)) {
            /** @var SchemaManagerFactory|\TYPO3\CMS\Core\Database\Schema\SchemaManager\CoreSchemaManagerFactory $coreSchemaFactory */
            $coreSchemaFactory = GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Database\Schema\SchemaManager\CoreSchemaManagerFactory::class
            );
            $configuration->setSchemaManagerFactory($coreSchemaFactory);
        }
        $driverConnection = DriverManager::getConnection($connectionParameters, $configuration);

        $schemaManager = $driverConnection->createSchemaManager();
        $isSQLite = self::isSQLite($driverConnection);

        // doctrine/dbal no longer supports createDatabase() and dropDatabase() statements. Guard it.
        if (!$isSQLite && in_array($databaseName, $schemaManager->listDatabases(), true)) {
            $schemaManager->dropDatabase($databaseName);
        } elseif ($isSQLite && is_file($databaseName)) {
            // Remove the sqlite file. Prior to Doctrine DBAL 4 the SqliteSchemaManager supported the
            // `dropDatabase()` method removing the file. Due to the removal we do it directly now.
            @unlink($databaseName);
        }
        try {
            // doctrine/dbal no longer supports createDatabase() and dropDatabase() statements. Guard it.
            // The engine will create the database file automatically.
            if (!$isSQLite) {
                $schemaManager->createDatabase($databaseName);
            }
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
     * @param non-empty-string $instancePath Absolute path to test instance
     * @return ContainerInterface
     */
    public function setUpBasicTypo3Bootstrap($instancePath): ContainerInterface
    {
        $_SERVER['PWD'] = $instancePath;
        // @todo: Remove when dropping support for v12
        $hasConsolidatedHttpEntryPoint = class_exists(CoreHttpApplication::class);
        $scriptPrefix = $hasConsolidatedHttpEntryPoint ? '' : 'typo3/';
        $_SERVER['argv'][0] = $scriptPrefix . 'index.php';

        // Reset state from a possible previous run
        GeneralUtility::purgeInstances();

        $classLoader = require $this->getPackagesPath() . '/autoload.php';
        // @todo: Remove else branch when dropping support for v12
        if ($hasConsolidatedHttpEntryPoint) {
            SystemEnvironmentBuilder::run(0, SystemEnvironmentBuilder::REQUESTTYPE_CLI, false);
        } else {
            SystemEnvironmentBuilder::run(1, SystemEnvironmentBuilder::REQUESTTYPE_BE | SystemEnvironmentBuilder::REQUESTTYPE_CLI);
        }
        $container = Bootstrap::init($classLoader);
        // Make sure output is not buffered, so command-line output can take place and
        // phpunit does not whine about changed output bufferings in tests.
        ob_end_clean();

        $this->dumpClassLoadingInformation();

        return $container;
    }

    /**
     * Dump class loading information
     */
    public function dumpClassLoadingInformation(): void
    {
        if (!ClassLoadingInformation::isClassLoadingInformationAvailable()) {
            ClassLoadingInformation::dumpClassLoadingInformation();
            ClassLoadingInformation::registerClassLoadingInformation();
        }
    }

    /**
     * Truncates all tables that need truncation.
     * Used in functional tests for test #2 and further ones to not create
     * the full database over and over again in between tests.
     */
    public function initializeTestDatabaseAndTruncateTables(string $dbPathSqlite = '', string $dbPathSqliteEmpty = ''): void
    {
        $driver = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driver'];
        if ($driver === 'pdo_sqlite' && $dbPathSqlite && $dbPathSqliteEmpty) {
            // Optimization for sqlite: Just copy the "empty" file created by first test.
            copy($dbPathSqliteEmpty, $dbPathSqlite);
            return;
        }

        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $platform = $connection->getDatabasePlatform();
        if ($platform instanceof DoctrineMariaDBPlatform || $platform instanceof DoctrineMySQLPlatform) {
            $this->truncateAllTablesForMysql();
        } else {
            $this->truncateAllTablesForOtherDatabases();
        }
    }

    /**
     * Truncates all tables for MySQL databases in an optimized way.
     *
     * This method tries to avoid the (expensive) truncate if possible:
     * - If the table has an auto-increment value (which usually is the `uid` column`) and that value has changed,
     *   this method will truncate the table.
     * - If the table does not have an auto-increment value, but it has at least one row (where the exact number does
     *   not matter), this method will truncate the table.
     * - Otherwise, this method will skip the truncate. (For tables without an auto-increment value, this means that
     *   the table either has not been touched at all beforehand, or that all records have already been deleted).
     */
    private function truncateAllTablesForMysql(): void
    {
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $databaseName = $connection->getDatabase();
        $tableNames = $connection->createSchemaManager()->listTableNames();

        if (empty($tableNames)) {
            // No tables to process
            return;
        }

        // Build a sub select to get joinable table with information if table has at least one row.
        // This is needed because information_schema.table_rows is not reliable enough for innodb engine.
        // see https://dev.mysql.com/doc/mysql-infoschema-excerpt/5.7/en/information-schema-tables-table.html TABLE_ROWS
        $fromTableUnionSubSelectQuery = [];
        foreach ($tableNames as $tableName) {
            $fromTableUnionSubSelectQuery[] = sprintf(
                ' SELECT %s AS table_name, exists(SELECT * FROm %s LIMIT 1) AS has_rows',
                $connection->quote($tableName),
                $connection->quoteIdentifier($tableName)
            );
        }
        $fromTableUnionSubSelectQuery = implode(' UNION ', $fromTableUnionSubSelectQuery);
        $query = sprintf(
            '
            SELECT
                table_real_rowcounts.*,
                information_schema.tables.AUTO_INCREMENT AS auto_increment
            FROM (%s) AS table_real_rowcounts
            INNER JOIN information_schema.tables ON (
                information_schema.tables.TABLE_SCHEMA = %s
                AND information_schema.tables.TABLE_NAME = table_real_rowcounts.table_name
            )',
            $fromTableUnionSubSelectQuery,
            $connection->quote($databaseName)
        );
        $result = $connection->executeQuery($query);
        while ($tableData = $result->fetchAssociative()) {
            $hasChangedAutoIncrement = ((int)$tableData['auto_increment']) > 1;
            $hasAtLeastOneRow = (bool)$tableData['has_rows'];
            $isChanged = $hasChangedAutoIncrement || $hasAtLeastOneRow;
            if ($isChanged) {
                $tableName = $tableData['table_name'];
                $connection->truncate($tableName);
            }
        }
    }

    /**
     * Truncates all tables without any database-specific optimizations.
     */
    private function truncateAllTablesForOtherDatabases(): void
    {
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);

        $schemaManager = $connection->createSchemaManager();
        foreach ($schemaManager->listTables() as $table) {
            $connection->truncate($table->getName());
            self::resetTableSequences($connection, $table->getName());
        }
    }

    /**
     * Load ext_tables.php files.
     * For functional and acceptance tests.
     */
    public function loadExtensionTables(): void
    {
        Bootstrap::loadExtTables();
    }

    /**
     * Create tables and import static rows.
     * For functional and acceptance tests.
     */
    public function createDatabaseStructure(ContainerInterface $container): void
    {
        try {
            $schemaMigrationService = $container->get(SchemaMigrator::class);
        } catch (ServiceNotFoundException) {
            // @todo: Remove when core v12 patch level 12.4.6 has been released which
            //        releases https://review.typo3.org/c/Packages/TYPO3.CMS/+/80530/5/typo3/sysext/core/Configuration/Services.yaml
            $schemaMigrationService = GeneralUtility::makeInstance(SchemaMigrator::class);
        }
        $sqlReader = GeneralUtility::makeInstance(SqlReader::class);
        $sqlCode = $sqlReader->getTablesDefinitionString();
        $createTableStatements = $sqlReader->getCreateTableStatementArray($sqlCode);
        $schemaMigrationService->install($createTableStatements);
    }

    /**
     * Perform post processing of database tables after an insert has been performed.
     * Doing this once per insert is rather slow, but due to the soft reference behavior
     * this needs to be done after every row to ensure consistent results.
     *
     * @param Connection $connection
     * @param string $tableName
     * @throws DBALException
     */
    public static function resetTableSequences(Connection $connection, string $tableName): void
    {
        $platform = $connection->getDatabasePlatform();
        if ($platform instanceof DoctrinePostgreSQLPlatform) {
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
                ->executeQuery()
                ->fetchAssociative();
            if ($row !== false) {
                $connection->executeStatement(
                    sprintf(
                        'SELECT SETVAL(%s, COALESCE(MAX(%s), 0)+1, FALSE) FROM %s',
                        $connection->quote($row['schemaname'] . '.' . $row['relname']),
                        $connection->quoteIdentifier($row['attname']),
                        $connection->quoteIdentifier($row['schemaname'] . '.' . $row['tablename'])
                    )
                );
            }
        } elseif (self::isSQLite($connection)) {
            // Drop eventually existing sqlite sequence for this table
            $connection->executeStatement(
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
     * @param string $from Absolute source path
     * @param string $to Absolute target path
     * @return bool True if all went well
     */
    protected function copyRecursive($from, $to)
    {
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
            } else {
                $success = copy($from . DIRECTORY_SEPARATOR . $file, $to . DIRECTORY_SEPARATOR . $file);
                $result = $result & $success;
            }
        }
        closedir($dir);
        return $result;
    }

    /**
     * Get Path to vendor dir
     * Since we are surly installed in the vendor dir, we can reuse the composer runtime information to get the
     * correct installation path of the testing-framework. With that we can calculate the package folder precisely,
     * avoiding invalid path determination when symlinks has been invited to the party.
     *
     * @return string Absolute path to vendor dir, without trailing slash
     */
    public function getPackagesPath(): string
    {
        return $this->composerPackageManager->getVendorPath();
    }

    /**
     * Returns the absolute path the TYPO3 document root.
     * This is the "original" document root, not the "instance" root for functional / acceptance tests.
     *
     * @return string the TYPO3 document root using Unix path separators
     */
    public function getWebRoot(): string
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
    protected function exitWithMessage($message): void
    {
        echo $message . chr(10);
        exit(1);
    }

    /**
     * @todo Replace usages by direct instanceof checks when
     *       TYPO3 v13.0 / Doctrine DBAL 4 is lowest supported version.
     * @throws DBALException
     */
    private static function isSQLite(Connection|DoctrineConnection $connection): bool
    {
        return $connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform;
    }
}
