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

/**
 * @internal Do not use this trait in own classes.
 */
trait AccessibleProxyTrait
{
    public function _call($methodName, ...$methodArguments)
    {
        if ($methodName === '') {
            throw new \InvalidArgumentException($methodName . ' must not be empty.', 1334663993);
        }
        return $this->$methodName(...$methodArguments);
    }

    public function _set($propertyName, $value)
    {
        if ($propertyName === '') {
            throw new \InvalidArgumentException($propertyName . ' must not be empty.', 1334664355);
        }
        $this->$propertyName = $value;
    }

    public function _get($propertyName)
    {
        if ($propertyName === '') {
            throw new \InvalidArgumentException($propertyName . ' must not be empty.', 1334664967);
        }
        return $this->$propertyName;
    }
}
