<?php

namespace TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Hook;

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

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Internal\TypoScriptInstruction;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\RequestBootstrap;

/**
 * Modifier for global TypoScript dynamically provided by test-case.
 */
class TypoScriptInstructionModifier implements SingletonInterface
{
    /**
     * @param array $parameters
     * @param TemplateService $service
     */
    public function apply(array $parameters, TemplateService $service)
    {
        $instruction = RequestBootstrap::getInternalRequest()
            ->getInstruction(TemplateService::class);
        if (!$instruction instanceof TypoScriptInstruction) {
            return;
        }

        $this->applyConstants($instruction, $service);
        $this->applyTypoScript($instruction, $service);
    }

    /**
     * @param TypoScriptInstruction $instruction
     * @param TemplateService $service
     */
    private function applyConstants(
        TypoScriptInstruction $instruction,
        TemplateService $service
    ) {
        if (empty($instruction->getConstants())) {
            return;
        }
        $service->constants[] = $this->compileAssignments(
            $instruction->getConstants()
        );
    }

    /**
     * @param TypoScriptInstruction $instruction
     * @param TemplateService $service
     */
    private function applyTypoScript(
        TypoScriptInstruction $instruction,
        TemplateService $service
    ) {
        if (empty($instruction->getTypoScript())) {
            return;
        }
        $service->config[] = $this->compileAssignments(
            $instruction->getTypoScript()
        );
    }

    /**
     * + input: ['a' => ['b' => 'c']]
     * + output: 'a.b = c'
     *
     * @param array $nestedArray
     * @return string
     */
    private function compileAssignments(array $nestedArray): string
    {
        $assignments = $this->flatten($nestedArray);
        array_walk(
            $assignments,
            function (&$value, $key) {
                $value = sprintf('%s = %s', $key, $value);
            }
        );
        return implode("\n", $assignments);
    }

    /**
     * + input: ['a' => ['b' => 'c']]
     * + output: ['a.b' => 'c']
     *
     * @param array $array
     * @param string $prefix
     * @return array
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge(
                    $result,
                    $this->flatten(
                        $value,
                        $prefix . rtrim($key, '.') . '.'
                    )
                );
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }
}
