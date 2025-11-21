<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Core\Functional\Framework\Frontend;

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

use TYPO3\CMS\Core\Attribute\AsAllowedCallable;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Section renderer for frontend responses.
 */
final class Renderer implements SingletonInterface
{
    private array $sections = [];
    private ContentObjectRenderer $cObj;

    #[AsAllowedCallable]
    public function parseValues(string $content, ?array $configuration = null): void
    {
        if (empty($content)) {
            return;
        }
        $values = json_decode($content, true);
        if (empty($values) || !is_array($values)) {
            return;
        }
        $asPrefix = (!empty($configuration['as']) ? $configuration['as'] . ':' : null);
        foreach ($values as $identifier => $structure) {
            $parser = $this->createParser();
            $parser->parse($structure);
            $section = [
                'structure' => $structure,
                'structurePaths' => $parser->getPaths(),
                'records' => $parser->getRecords(),
            ];
            $this->addSection($section, $asPrefix . $identifier);
        }
    }

    /**
     * Possible structure of $configuration:
     * {
     *   values {
     *     propertyA.data = tsfe:id
     *     propertyB.children {
     *       propertyB1.data = page:id
     *       propertyB2.data = page:pid
     *       propertyB2.intval = 1
     *     }
     *   }
     *   as = CustomData
     * }
     */
    #[AsAllowedCallable]
    public function renderValues(string $content, ?array $configuration = null): void
    {
        if (empty($configuration['values.'])) {
            return;
        }
        $as = (!empty($configuration['as']) ? $configuration['as'] : null);
        $this->addSection($this->stdWrapValues($configuration['values.']), $as);
    }

    public function addSection(array $section, ?string $as = null): void
    {
        if (!empty($as)) {
            $this->sections[$as] = $section;
        } else {
            $this->sections[] = $section;
        }
    }

    #[AsAllowedCallable]
    public function renderSections(): string
    {
        return json_encode($this->sections);
    }

    /**
     * Possible structure of $values:
     * {
     *   propertyA.data = tsfe:id
     *   propertyB.children {
     *     propertyB1.data = page:id
     *     propertyB2.data = page:pid
     *     propertyB2.intval = 1
     *   }
     * }
     */
    private function stdWrapValues(array $values): array
    {
        $renderedValues = [];
        foreach ($values as $propertyName => $propertyInstruction) {
            $plainPropertyName = rtrim($propertyName, '.');
            if (!empty($propertyInstruction['children.'])) {
                $renderedValues[$plainPropertyName] = $this->stdWrapValues(
                    $propertyInstruction['children.']
                );
            } else {
                $renderedValues[$plainPropertyName] = $this->cObj->stdWrap(
                    '',
                    $propertyInstruction
                );
            }
        }
        return $renderedValues;
    }

    private function createParser(): Parser
    {
        return GeneralUtility::makeInstance(Parser::class);
    }

    /**
     * This is called from UserContentObject via ContentObjectRenderer->callUserFunction()
     * for nested menu items - those use a USER content object for getDataAsJson().
     */
    public function setContentObjectRenderer(ContentObjectRenderer $cObj): void
    {
        $this->cObj = $cObj;
    }
}
