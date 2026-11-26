<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Core\Functional\Attribute;

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
 * Lets functional test case classes with the same instance configuration share one
 * test instance.
 *
 * By default, every test case class gets its own instance: its own directory, its own
 * database and schema, and its own compiled dependency injection container. Setting up
 * an instance takes about a second, a further test in it a fraction of that, so for
 * test case classes with few tests each, setting up instances dominates the run.
 *
 * Test case classes carrying this attribute - directly or through a parent class - are
 * identified by their instance configuration instead of their class name: the
 * extensions to load, the paths to link and provide, the configuration to use, the
 * folders to create, whether the database is initialized, and which class implements
 * the database setup. Classes configured the same way share one instance. It is set up
 * once, and every later test only resets the database, as the second test of a class
 * always did.
 *
 * Requirements for test case classes using it:
 *
 * - The instance configuration is declared as default values of the properties named
 *   above. Assigning them at runtime, in setUp() for instance, is detected and fails,
 *   because the instance is identified before setUp() runs.
 * - Tests do not leave files in the instance that change the behavior of other tests.
 *   Database rows, the "core" cache group and settings.php are reset when the instance
 *   is handed over to another test case class.
 *
 * Example, sharing the instance between all test case classes of an extension:
 *
 *   #[ShareInstance]
 *   abstract class AbstractFunctionalTestCase extends FunctionalTestCase
 *   {
 *       protected array $testExtensionsToLoad = ['vendor/my-extension'];
 *   }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ShareInstance {}
