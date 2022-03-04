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

/**
 * This interface defines the methods provided by TYPO3\TestingFramework\Core\TestCase::getAccessibleMock.
 * Do not implement this interface in own classes. This should only be implemented by testing-framework classes.
 */
interface AccessibleObjectInterface
{
    /**
     * Calls the method $method using call_user_func* and returns its return value.
     *
     * @param string $methodName name of method to call, must not be empty
     * @param mixed ...$methodArguments additional arguments for method
     * @return mixed the return value from the method $methodName
     */
    public function _call($methodName, ...$methodArguments);

    /**
     * Sets the value of a property.
     *
     * @param string $propertyName name of property to set value for, must not be empty
     * @param mixed $value the new value for the property defined in $propertyName
     */
    public function _set($propertyName, $value);

    /**
     * Gets the value of the given property.
     *
     * @param string $propertyName name of property to return value of, must not be empty
     *
     * @return mixed the value of the property $propertyName
     */
    public function _get($propertyName);
}
