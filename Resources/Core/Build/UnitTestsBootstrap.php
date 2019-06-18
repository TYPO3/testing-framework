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

    // These if's are for core testing (package typo3/cms) only. cms-composer-installer does
    // not create the autoload-include.php file that sets these env vars and sets composer
    // mode to true. testing-framework can not be used without composer anyway, so it is safe
    // to do this here. This way it does not matter if 'bin/phpunit' or 'vendor/phpunit/phpunit/phpunit'
    // is called to run the tests since the 'relative to entry script' path calculation within
    // SystemEnvironmentBuilder is not used. However, the binary must be called from the document
    // root since getWebRoot() uses 'getcwd()'.
    if (!getenv('TYPO3_PATH_ROOT')) {
        putenv('TYPO3_PATH_ROOT=' . rtrim($testbase->getWebRoot(), '/'));
    }
    if (!getenv('TYPO3_PATH_WEB')) {
        putenv('TYPO3_PATH_WEB=' . rtrim($testbase->getWebRoot(), '/'));
    }

    $testbase->defineSitePath();
    $testbase->defineTypo3ModeBe();
    $testbase->setTypo3TestingContext();

    $requestType = \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_BE | \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_CLI;
    \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::run(0, $requestType);

    $testbase->createDirectory(\TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/typo3conf/ext');
    $testbase->createDirectory(\TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/typo3temp/assets');
    $testbase->createDirectory(\TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/typo3temp/var/tests');
    $testbase->createDirectory(\TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/typo3temp/var/transient');

    // Retrieve an instance of class loader and inject to core bootstrap
    $classLoader = require $testbase->getPackagesPath() . '/autoload.php';
    \TYPO3\CMS\Core\Core\Bootstrap::initializeClassLoader($classLoader);

    \TYPO3\CMS\Core\Core\Bootstrap::baseSetup();

    // Initialize default TYPO3_CONF_VARS
    $configurationManager = new \TYPO3\CMS\Core\Configuration\ConfigurationManager();
    $GLOBALS['TYPO3_CONF_VARS'] = $configurationManager->getDefaultConfiguration();
    // Avoid failing tests that rely on HTTP_HOST retrieval
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*';

    $cache = new \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend(
        'core',
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