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

namespace TYPO3\JsonResponse\EventListener;

use TYPO3\CMS\Core\TypoScript\IncludeTree\Event\AfterTemplatesHaveBeenDeterminedEvent;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Internal\TypoScriptInstruction;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Listener which modifies the form data to add TypoScript as found
 * in InternalRequest TypoScriptInstruction.
 *
 * This adds global TypoScript as last "fake" row to be dynamically provided by test-case.
 */
final class AddTypoScriptFromInternalRequest
{
    public function __invoke(AfterTemplatesHaveBeenDeterminedEvent $event): void
    {
        if (method_exists($event, 'getRequest')) {
            $request = $event->getRequest();
        } else {
            // This is a compat layer for 12.0 exclusively, 12.1 will have getRequest() in the event.
            // See https://review.typo3.org/c/Packages/TYPO3.CMS/+/75995/
            // @todo: Remove if/else when 12.1 has been released and use getRequest() only.
            $request = $GLOBALS['TYPO3_REQUEST'];
        }
        if (!$request instanceof InternalRequest) {
            return;
        }
        $instruction = $request->getInstruction('TypoScript');
        if (!$instruction instanceof TypoScriptInstruction || (empty($instruction->getConstants()) && empty($instruction->getTypoScript()))) {
            return;
        }
        $newTemplateRow = [
            'uid' => PHP_INT_MAX,
            'pid' => PHP_INT_MAX,
            'tstamp' => time(),
            'crdate' => time(),
            'deleted' => 0,
            'starttime' => 0,
            'endtime' => 0,
            'sorting' => 0,
            'description' => null,
            't3_origuid' => 0,
            'title' => 'Testing Framework dynamic TypoScript for functional tests',
            'root' => 0,
            'clear' => 0,
            'include_static_file' => null,
            'constants' => $this->getConstants($instruction),
            'config' => $this->getSetup($instruction),
            'basedOn' => null,
            'includeStaticAfterBasedOn' => 0,
            'static_file_mode' => 0,
        ];
        $templateRows = $event->getTemplateRows();
        $templateRows[] = $newTemplateRow;
        $event->setTemplateRows($templateRows);
    }

    private function getConstants(TypoScriptInstruction $instruction): string
    {
        if (empty($instruction->getConstants())) {
            return '';
        }
        return $this->compileAssignments($instruction->getConstants());
    }

    private function getSetup(TypoScriptInstruction $instruction): string
    {
        if (empty($instruction->getTypoScript())) {
            return '';
        }
        return $this->compileAssignments($instruction->getTypoScript());
    }

    /**
     * + input: ['a' => ['b' => 'c']]
     * + output: 'a.b = c'
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
