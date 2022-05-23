<?php

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
use PHPUnit\Framework\TestCase;

/**
 * The mother of all test cases.
 *
 * Don't sub class this test case but rather choose a more specialized base test case,
 * such as UnitTestCase or FunctionalTestCase
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * Creates a mock object which allows for calling protected methods and access of protected properties.
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
        $originalClassName,
        $methods = [],
        array $arguments = [],
        $mockClassName = '',
        $callOriginalConstructor = true,
        $callOriginalClone = true,
        $callAutoload = true
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
     * the last parameter
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
        $originalClassName,
        array $arguments = [],
        $mockClassName = '',
        $callOriginalConstructor = true,
        $callOriginalClone = true,
        $callAutoload = true,
        $mockedMethods = []
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
    protected function buildAccessibleProxy($className)
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
    protected function getUniqueId($prefix = '')
    {
        $uniqueId = uniqid(mt_rand(), true);
        return $prefix . str_replace('.', '', $uniqueId);
    }
}
