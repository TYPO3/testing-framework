<?php

declare(strict_types=1);

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

namespace TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Internal;

/**
 * Model of arbitrary array value instruction
 */
final class ArrayValueInstruction implements InstructionInterface
{
    private array $array = [];
    private string $identifier;

    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function withArray(array $array): self
    {
        $target = clone $this;
        $target->array = $array;
        return $target;
    }

    public function getArray(): array
    {
        return $this->array;
    }
}
