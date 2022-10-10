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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\RiskyTestError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Util\ErrorHandler;

/**
 * The mother of all test cases.
 *
 * Don't sub class this test case but rather choose a more specialized base test case,
 * such as UnitTestCase or FunctionalTestCase
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * Holds the state of error_reporting during setUp() phase,
     * which will checked in tearDown() phase to ensure that a
     * test does not change error_reporting behaviour between tests.
     */
    private ?int $backupErrorReporting = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupErrorReporting = error_reporting();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Verify no dangling error handler is registered. This might happen when
        // tests register an own error handler which is not reset again. This error
        // handler then may "eat" error of subsequent tests.
        // Register a dummy error handler to retrieve *previous* one and unregister dummy again,
        // then verify previous is the phpunit error handler. This will mark the one test that
        // fails to unset/restore it's custom error handler as "risky".
        $previousErrorHandler = set_error_handler(function (int $errorNumber, string $errorString, string $errorFile, int $errorLine): bool {return false;});
        restore_error_handler();
        if (!$previousErrorHandler instanceof ErrorHandler) {
            throw new RiskyTestError(
                'tearDown() check: A dangling error handler has been found. Use restore_error_handler() to unset it.',
                1634490417
            );
        }

        // Verify no dangling exception handler is registered. Same scenario as with error handlers.
        $previousExceptionHandler = set_exception_handler(function () {});
        restore_exception_handler();
        if ($previousExceptionHandler !== null) {
            throw new RiskyTestError(
                'tearDown() check: A dangling exception handler has been found. Use restore_exception_handler() to unset it.',
                1634490418
            );
        }

        if ($this->backupErrorReporting !== null) {
            $backupErrorReporting = $this->backupErrorReporting;
            $this->backupErrorReporting = null;
            $currentErrorReporting = error_reporting();
            if ($currentErrorReporting !== $backupErrorReporting) {
                error_reporting($backupErrorReporting);
            }
            if ($backupErrorReporting !== $currentErrorReporting) {
                throw new RiskyTestError(
                    'tearDown() integrity check found changed error_reporting. Before was '
                    . $backupErrorReporting . ' compared to current ' . $currentErrorReporting . ' in '
                    . '"' . get_class($this) . '".'
                    . 'Please check and verify that this is intended and add proper cleanup to the test.',
                    1665251711
                );
            }
        }
    }

    /**
     * Creates a mock object which allows for calling protected methods and access of protected properties.
     *
     * Note: This method has no native return types on purpose, but only PHPDoc return type annotations.
     * The reason is that the combination of "union types with generics in PHPDoc" and "a subset of those types as
     * native types, but without the generics" tends to confuse PhpStorm's static type analysis (which we want to avoid).
     *
     * @template T of object
     * @param class-string<T> $originalClassName name of class to create the mock object of
     * @param string[]|null $methods name of the methods to mock, null for "mock no methods"
     * @param array $arguments arguments to pass to constructor
     * @param string $mockClassName the class name to use for the mock class
     * @param bool $callOriginalConstructor whether to call the constructor
     * @param bool $callOriginalClone whether to call the __clone method
     * @param bool $callAutoload whether to call any autoload function
     *
     * @return MockObject&AccessibleObjectInterface&T a mock of `$originalClassName` with access methods added
     *
     * @throws \InvalidArgumentException
     */
    protected function getAccessibleMock(
        string $originalClassName,
        array|null $methods = [],
        array $arguments = [],
        string $mockClassName = '',
        bool $callOriginalConstructor = true,
        bool $callOriginalClone = true,
        bool $callAutoload = true
    ) {
        if ($originalClassName === '') {
            throw new \InvalidArgumentException('$originalClassName must not be empty.', 1334701880);
        }

        $mockBuilder = $this->getMockBuilder($this->buildAccessibleProxy($originalClassName))
            ->setMethods($methods)
            ->setConstructorArgs($arguments)
            ->setMockClassName($mockClassName);

        if (!$callOriginalConstructor) {
            $mockBuilder->disableOriginalConstructor();
        }

        if (!$callOriginalClone) {
            $mockBuilder->disableOriginalClone();
        }

        if (!$callAutoload) {
            $mockBuilder->disableAutoload();
        }

        return $mockBuilder->getMock();
    }

    /**
     * Returns a mock object which allows for calling protected methods and access
     * of protected properties. Concrete methods to mock can be specified with
     * the last parameter.
     *
     * Note: This method has no native return types on purpose, but only PHPDoc return type annotations.
     * The reason is that the combination of "union types with generics in PHPDoc" and "a subset of those types as
     * native types, but without the generics" tends to confuse PhpStorm's static type analysis (which we want to avoid).
     *
     * @template T of object
     * @param class-string<T> $originalClassName Full qualified name of the original class
     * @param array $arguments
     * @param string $mockClassName
     * @param bool $callOriginalConstructor
     * @param bool $callOriginalClone
     * @param bool $callAutoload
     * @param array $mockedMethods
     * @return MockObject&AccessibleObjectInterface&T
     *
     * @throws \InvalidArgumentException
     */
    protected function getAccessibleMockForAbstractClass(
        string $originalClassName,
        array $arguments = [],
        string $mockClassName = '',
        bool $callOriginalConstructor = true,
        bool $callOriginalClone = true,
        bool $callAutoload = true,
        array $mockedMethods = []
    ) {
        if ($originalClassName === '') {
            throw new \InvalidArgumentException('$originalClassName must not be empty.', 1384268260);
        }

        return $this->getMockForAbstractClass(
            $this->buildAccessibleProxy($originalClassName),
            $arguments,
            $mockClassName,
            $callOriginalConstructor,
            $callOriginalClone,
            $callAutoload,
            $mockedMethods
        );
    }

    /**
     * Creates a proxy class of the specified class which allows
     * for calling even protected methods and access of protected properties.
     *
     * @template T of object
     * @param class-string<T> $className Name of class to make available
     * @return class-string<AccessibleObjectInterface&T> Fully qualified name of the built class
     */
    protected function buildAccessibleProxy(string $className): string
    {
        $accessibleClassName = $this->getUniqueId('Tx_Phpunit_AccessibleProxy');
        $reflectionClass = new \ReflectionClass($className);
        eval(
            ($reflectionClass->isAbstract() ? 'abstract ' : '') . 'class ' . $accessibleClassName .
                ' extends ' . $className . ' implements ' . AccessibleObjectInterface::class . ' {' .
                    ' use ' . AccessibleProxyTrait::class . ';' .
            '}'
        );
        return $accessibleClassName;
    }

    /**
     * Create and return a unique id optionally prepended by a given string
     *
     * This function is used because on windows and in cygwin environments uniqid() has a resolution of one second which
     * results in identical ids if simply uniqid('Foo'); is called.
     *
     * @param string $prefix
     * @return string
     */
    protected function getUniqueId(string $prefix = ''): string
    {
        $uniqueId = uniqid((string)mt_rand(), true);
        return $prefix . str_replace('.', '', $uniqueId);
    }
}
