<?php
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

/**
 * This file is defined in UnitTests.xml and called by phpunit
 * before instantiating the test suites, it must also be included
 * with phpunit parameter --bootstrap if executing single test case classes.
 *
 * Run whole core unit test suite, example:
 * - cd /var/www/t3master/foo  # Document root of TYPO3 CMS instance (location of index.php)
 * - typo3/../bin/phpunit -c components/testing_framework/core/Build/UnitTests.xml
 *
 * Run single test case, example:
 * - cd /var/www/t3master/foo  # Document root of TYPO3 CMS instance (location of index.php)
 * - typo3/../bin/phpunit -c components/testing_framework/core/Build/UnitTests.xml
 *     typo3/sysext/core/Tests/Unit/DataHandling/DataHandlerTest.php
 */
call_user_func(function () {
    $testbase = new \TYPO3\TestingFramework\Core\Testbase();
    $testbase->enableDisplayErrors();
    $testbase->defineBaseConstants();
    $testbase->defineSitePath();
    $testbase->defineTypo3ModeBe();
    $testbase->setTypo3TestingContext();
    $testbase->definePackagesPath();
    $testbase->createDirectory(PATH_site . 'typo3conf/ext');
    $testbase->createDirectory(PATH_site . 'typo3temp/assets');
    $testbase->createDirectory(PATH_site . 'typo3temp/var/tests');
    $testbase->createDirectory(PATH_site . 'typo3temp/var/transient');
    $testbase->createDirectory(PATH_site . 'uploads');

    // Retrieve an instance of class loader and inject to core bootstrap
    $classLoaderFilepath = TYPO3_PATH_PACKAGES . 'autoload.php';
    if (!file_exists($classLoaderFilepath)) {
        die('ClassLoader can\'t be loaded. Please check your path or set an environment variable \'TYPO3_PATH_ROOT\' to your root path.');
    }
    $classLoader = require $classLoaderFilepath;

    $requestType = \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_BE | \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_CLI;
    \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::run(0, $requestType);
    \TYPO3\CMS\Core\Core\Bootstrap::initializeClassLoader($classLoader);
    \TYPO3\CMS\Core\Core\Bootstrap::baseSetup();

    // Initialize default TYPO3_CONF_VARS
    $configurationManager = new \TYPO3\CMS\Core\Configuration\ConfigurationManager();
    $GLOBALS['TYPO3_CONF_VARS'] = $configurationManager->getDefaultConfiguration();
    // Avoid failing tests that rely on HTTP_HOST retrieval
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*';

    $cache = new \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend(
        'cache_core',
	new \TYPO3\CMS\Core\Cache\Backend\NullBackend('production', [])
    );
    // Set all packages to active
    $packageManager = \TYPO3\CMS\Core\Core\Bootstrap::createPackageManager(\TYPO3\CMS\Core\Package\UnitTestPackageManager::class, $cache);

    \TYPO3\CMS\Core\Utility\GeneralUtility::setSingletonInstance(\TYPO3\CMS\Core\Package\PackageManager::class, $packageManager);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::setPackageManager($packageManager);

    if (!\TYPO3\CMS\Core\Core\Environment::isComposerMode()) {
        // Dump autoload info if in non composer mode
        \TYPO3\CMS\Core\Core\ClassLoadingInformation::dumpClassLoadingInformation();
        \TYPO3\CMS\Core\Core\ClassLoadingInformation::registerClassLoadingInformation();
    }

    \TYPO3\CMS\Core\Utility\GeneralUtility::purgeInstances();
});
