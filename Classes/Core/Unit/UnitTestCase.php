<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Core\Unit;

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

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\TestingFramework\Core\BaseTestCase;

/**
 * Base test case for unit tests.
 *
 * This class currently only inherits the base test case. However, it is recommended
 * to extend this class for unit test cases instead of the base test case because if,
 * at some point, specific behavior needs to be implemented for unit tests, your test cases
 * will profit from it automatically.
 */
abstract class UnitTestCase extends BaseTestCase
{
    /**
     * If set to true, setUp() will back up the state of the
     * TYPO3\CMS\Core\Core\Environment class and restore it
     * in tearDown().
     *
     * This is needed for tests that reset state of Environment
     * by calling Environment::init() to for instance fake paths
     * or force windows environment.
     */
    protected bool $backupEnvironment = false;

    /**
     * If set to true, tearDown() will purge singleton instances created by the test.
     *
     * Unit tests that trigger singleton creation via makeInstance() should set this
     * to true to reset the framework internal singleton state after test execution.
     *
     * A test having this property set to true declares that the system under test
     * includes functionality that does change global framework state. This bit of
     * information is the reason why tearDown() does not reset singletons automatically.
     * tearDown() will make the test fail if that property has not been set to true
     * and if there are remaining singletons after test execution.
     */
    protected bool $resetSingletonInstances = false;

    /**
     * Absolute path to files that should be removed after a test.
     * Handled in tearDown. Tests can register here to get any files
     * within typo3temp/ or typo3conf/ext cleaned up again.
     *
     * @var non-empty-string[]
     */
    protected array $testFilesToDelete = [];

    /**
     * Holds state of TYPO3\CMS\Core\Core\Environment if
     * $this->backupEnvironment has been set to true in a test case
     *
     * @var array<string, mixed>
     */
    private array $backedUpEnvironment = [];

    /**
     * This property helps to determine if method chain of
     * setUp() is valid down to this class, which is checked
     * in tearDown() which is more unlikely to be overridden
     * in tests.
     *
     * @var bool
     */
    private bool $setUpMethodCallChainValid = false;

    /**
     * Generic setUp()
     */
    protected function setUp(): void
    {
        $this->setUpMethodCallChainValid = true;
        if ($this->backupEnvironment === true) {
            $this->backupEnvironment();
        }
        parent::setUp();
    }

    /**
     * Unset all additional properties of test classes to help PHP
     * garbage collection. This reduces memory footprint with lots
     * of tests.
     *
     * If overwriting tearDown() in test classes, please call
     * parent::tearDown() at the end. Unsetting of own properties
     * is not needed this way.
     *
     * @throws \RuntimeException
     */
    protected function tearDown(): void
    {
        // Restore Environment::class is asked for
        if ($this->backupEnvironment === true) {
            $this->restoreEnvironment();
        }

        // Flush the static $indpEnvCache
        // between test runs to prevent side effects from this cache.
        GeneralUtility::flushInternalRuntimeCaches();

        // GeneralUtility::makeInstance() singleton handling
        if ($this->resetSingletonInstances === true) {
            // Reset singletons if asked for by test setup
            GeneralUtility::resetSingletonInstances([]);
        } else {
            // But fail if there are instances left and the test did not ask for reset
            $singletonInstances = GeneralUtility::getSingletonInstances();
            // Reset singletons anyway to not let all further tests fail
            GeneralUtility::resetSingletonInstances([]);
            if (!empty($singletonInstances)) {
                self::fail(
                    'tearDown() integrity check found left over singleton instances in GeneralUtility::makeInstance()'
                    . ' instance list. The test should probably set \'$this->resetSingletonInstances = true;\' to'
                    . ' reset this framework state change. Found singletons: ' . implode(', ', array_keys($singletonInstances))
                );
            }
        }

        // Delete registered test files and directories
        foreach ($this->testFilesToDelete as $absoluteFileName) {
            $absoluteFileName = GeneralUtility::fixWindowsFilePath(PathUtility::getCanonicalPath($absoluteFileName));
            if (!GeneralUtility::validPathStr($absoluteFileName)) {
                throw new \RuntimeException('tearDown() cleanup: Filename contains illegal characters', 1410633087);
            }
            if (!str_starts_with($absoluteFileName, Environment::getVarPath())
                && !str_starts_with($absoluteFileName, Environment::getPublicPath() . '/typo3temp/')
            ) {
                throw new \RuntimeException(
                    'tearDown() cleanup:  Files to delete must be within ' . Environment::getVarPath() . ' or ' . Environment::getPublicPath() . '/typo3temp/',
                    1410633412
                );
            }
            // file_exists returns false for links pointing to not existing targets, so handle links before next check.
            if (@is_link($absoluteFileName) || @is_file($absoluteFileName)) {
                unlink($absoluteFileName);
            } elseif (@is_dir($absoluteFileName)) {
                GeneralUtility::rmdir($absoluteFileName, true);
            } else {
                throw new \RuntimeException('tearDown() cleanup: File, link or directory does not exist', 1410633510);
            }
        }
        $this->testFilesToDelete = [];

        // Verify all instances a test may have added using addInstance() have
        // been consumed from the GeneralUtility::makeInstance() instance stack.
        // This integrity check is to avoid side effects on tests run afterwards.
        $instanceObjectsArray = GeneralUtility::getInstances();
        $notCleanInstances = [];
        foreach ($instanceObjectsArray as $instanceObjectArray) {
            if (!empty($instanceObjectArray)) {
                foreach ($instanceObjectArray as $instance) {
                    $notCleanInstances[] = $instance;
                }
            }
        }
        if (!empty($notCleanInstances)) {
            // Reset instance list (including singletons & container) to not let all further tests fail
            GeneralUtility::purgeInstances();
            self::fail(
                'tearDown() integrity check found left over instances in GeneralUtility::makeInstance() instance list.'
                . ' Always consume instances added via GeneralUtility::addInstance() in your test by the test subject.'
                . ' Found instances of these classes: ' . implode(', ', array_keys($notCleanInstances))
            );
        }

        if ($this->setUpMethodCallChainValid === false) {
            self::fail(
                'tearDown() integrity check detected that setUp() has a broken parent call chain.'
                . ' Please check that setUp() methods properly calls parent::setUp(), starting from "' . static::class . '"'
            );
        }

        parent::tearDown();
    }

    /**
     * Helper method used in setUp() if $this->backupEnvironment is true
     * to back up current state of the Environment::class
     */
    private function backupEnvironment(): void
    {
        $this->backedUpEnvironment['context'] = Environment::getContext();
        $this->backedUpEnvironment['isCli'] = Environment::isCli();
        $this->backedUpEnvironment['composerMode'] = Environment::isComposerMode();
        $this->backedUpEnvironment['projectPath'] = Environment::getProjectPath();
        $this->backedUpEnvironment['publicPath'] = Environment::getPublicPath();
        $this->backedUpEnvironment['varPath'] = Environment::getVarPath();
        $this->backedUpEnvironment['configPath'] = Environment::getConfigPath();
        $this->backedUpEnvironment['currentScript'] = Environment::getCurrentScript();
        $this->backedUpEnvironment['isOsWindows'] = Environment::isWindows();
    }

    /**
     * Helper method used in tearDown() if $this->backupEnvironment is true
     * to reset state of Environment::class
     */
    private function restoreEnvironment(): void
    {
        Environment::initialize(
            $this->backedUpEnvironment['context'],
            $this->backedUpEnvironment['isCli'],
            $this->backedUpEnvironment['composerMode'],
            $this->backedUpEnvironment['projectPath'],
            $this->backedUpEnvironment['publicPath'],
            $this->backedUpEnvironment['varPath'],
            $this->backedUpEnvironment['configPath'],
            $this->backedUpEnvironment['currentScript'],
            $this->backedUpEnvironment['isOsWindows'] ? 'WINDOWS' : 'UNIX'
        );
    }
}
