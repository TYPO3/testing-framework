<?php

namespace TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Internal;

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
 * Model of arbitrary array value instruction
 */
class ArrayValueInstruction extends AbstractInstruction
{
    /**
     * @var array
     */
    protected $array = [];

    /**
     * @param array $array
     * @return static
     */
    public function withArray(array $array): self
    {
        $target = clone $this;
        $target->array = $array;
        return $target;
    }

    /**
     * @return array
     */
    public function getArray(): array
    {
        return $this->array;
    }
}
