<?php
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
 *
 */
abstract class UnitTestCase extends BaseTestCase
{
    /**
     * @todo make LoadedExtensionsArray serializable instead
     *
     * @var array
     */
    protected $backupGlobalsBlacklist = ['TYPO3_LOADED_EXT'];

    /**
     * Absolute path to files that should be removed after a test.
     * Handled in tearDown. Tests can register here to get any files
     * within typo3temp/ or typo3conf/ext cleaned up again.
     *
     * @var array
     */
    protected $testFilesToDelete = [];

    /**
     * This variable can be set to true if unit tests execute
     * code that is not E_NOTICE free to not let the test fail.
     *
     * @deprecated This setting will be removed if TYPO3 core does not need it any longer.
     *
     * @var bool
     */
    protected static $suppressNotices = false;

    /**
     * @var int Backup variable of current error reporting
     */
    private static $backupErrorReporting;

    /**
     * The Environment object is used in TYPO3 to pass immutable settings
     * like paths and system info around.
     * It may be created in tests, but needs to be restored afterwards
     * The array holds the original data to reset the Environment object
     * after test run.
     *
     * @var array
     */
    private $backedUpEnvironment = [];

    /**
     * Set error reporting to trigger or suppress E_NOTICE
     */
    public static function setUpBeforeClass()
    {
        $errorReporting = self::$backupErrorReporting = error_reporting();
        if (static::$suppressNotices === false) {
            error_reporting($errorReporting | E_NOTICE);
        } else {
            error_reporting($errorReporting & ~E_NOTICE);
        }
    }

    /**
     * Reset error reporting to original state
     */
    public static function tearDownAfterClass()
    {
        error_reporting(self::$backupErrorReporting);
    }

    protected function setUp()
    {
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
     * @return void
     */
    protected function tearDown()
    {
        // Unset properties of test classes to safe memory
        $reflection = new \ReflectionObject($this);
        foreach ($reflection->getProperties() as $property) {
            $declaringClass = $property->getDeclaringClass()->getName();
            if (
                !$property->isStatic()
                && $declaringClass !== \TYPO3\TestingFramework\Core\Unit\UnitTestCase::class
                && $declaringClass !== \TYPO3\TestingFramework\Core\BaseTestCase::class
                && strpos($property->getDeclaringClass()->getName(), 'PHPUnit') !== 0
            ) {
                $propertyName = $property->getName();
                unset($this->$propertyName);
            }
        }
        unset($reflection);

        // Delete registered test files and directories
        foreach ($this->testFilesToDelete as $absoluteFileName) {
            $absoluteFileName = GeneralUtility::fixWindowsFilePath(PathUtility::getCanonicalPath($absoluteFileName));
            if (!GeneralUtility::validPathStr($absoluteFileName)) {
                throw new \RuntimeException('tearDown() cleanup: Filename contains illegal characters', 1410633087);
            }
            if (strpos($absoluteFileName, PATH_site . 'typo3temp/var/') !== 0) {
                throw new \RuntimeException(
                    'tearDown() cleanup:  Files to delete must be within typo3temp/var/',
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
                foreach($instanceObjectArray as $instance) {
                    $notCleanInstances[] = $instance;
                }
            }
        }
        // Let the test fail if there were instances left and give some message on why it fails
        self::assertEquals(
            [],
            $notCleanInstances,
            'tearDown() integrity check found left over instances in GeneralUtility::makeInstance() instance stack.'
            . ' Always consume instances added via GeneralUtility::addInstance() in your test by the test subject.'
        );

        if ($this->backupEnvironment === true) {
            $this->restoreEnvironment();
        }
    }

    /**
     * before using Environment::initialize() in tests, backup the current data to be able to restore it afterwards
     */
    protected function backupEnvironment() {
        $this->backedUpEnvironment['context'] = TYPO3\CMS\Core\Core\Environment::getContext();
        $this->backedUpEnvironment['isCli'] = TYPO3\CMS\Core\Core\Environment::isCli();
        $this->backedUpEnvironment['composerMode'] = TYPO3\CMS\Core\Core\Environment::isComposerMode();
        $this->backedUpEnvironment['projectPath'] = TYPO3\CMS\Core\Core\Environment::getProjectPath();
        $this->backedUpEnvironment['publicPath'] = TYPO3\CMS\Core\Core\Environment::getPublicPath();
        $this->backedUpEnvironment['varPath'] = TYPO3\CMS\Core\Core\Environment::getVarPath();
        $this->backedUpEnvironment['configPath'] = TYPO3\CMS\Core\Core\Environment::getConfigPath();
        $this->backedUpEnvironment['currentScript'] = TYPO3\CMS\Core\Core\Environment::getCurrentScript();
        $this->backedUpEnvironment['isOsWindows'] = TYPO3\CMS\Core\Core\Environment::isWindows();
    }

    /**
     * restore the Environment object after usage
     */
    protected function restoreEnvironment() {
        TYPO3\CMS\Core\Core\Environment::initialize(
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
