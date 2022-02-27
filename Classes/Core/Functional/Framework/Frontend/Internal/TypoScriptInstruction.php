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
 * Model of TypoScript instruction
 */
class TypoScriptInstruction extends AbstractInstruction
{
    /**
     * @var array
     */
    protected $constants;

    /**
     * @var array
     */
    protected $typoScript;

    /**
     * @param array $constants
     * @return static
     */
    public function withConstants(array $constants): self
    {
        $target = clone $this;
        $target->constants = $constants;
        return $target;
    }

    /**
     * @param array $typoScript
     * @return static
     */
    public function withTypoScript(array $typoScript): self
    {
        $target = clone $this;
        $target->typoScript = $typoScript;
        return $target;
    }

    /**
     * @return array
     */
    public function getConstants(): ?array
    {
        return $this->constants;
    }

    /**
     * @return array
     */
    public function getTypoScript(): ?array
    {
        return $this->typoScript;
    }
}
