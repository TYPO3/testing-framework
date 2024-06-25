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
 * This allows adding Frontend TypoScript when executing Frontend sub requests in functional tests.
 * When a Frontend InternalRequest is added, an instance of this object can be added using "->withInstruction()"
 *
 * TypoScript constants and setup added here are automatically added when executing the Frontend request
 * using an event in ext:json_response, which is loaded for all functional tests by default.
 */
final class TypoScriptInstruction implements InstructionInterface
{
    private ?array $constants = null;
    private ?array $typoScript = null;

    public function getIdentifier(): string
    {
        return 'TypoScript';
    }

    public function withConstants(array $constants): self
    {
        $target = clone $this;
        $target->constants = $constants;
        return $target;
    }

    public function withTypoScript(array $typoScript): self
    {
        $target = clone $this;
        $target->typoScript = $typoScript;
        return $target;
    }

    public function getConstants(): ?array
    {
        return $this->constants;
    }

    public function getTypoScript(): ?array
    {
        return $this->typoScript;
    }
}
