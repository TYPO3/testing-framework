<?php

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

/**
 * Model of frontend response content
 */
final class ResponseSection
{
    private string $identifier;
    private array $structure;
    private array $structurePaths;
    private array $records;

    public function __construct(string $identifier, array $data)
    {
        if (!isset($data['structure'])
            && !isset($data['structurePaths'])
            && !isset($data['records'])
        ) {
            throw new \RuntimeException(
                'Empty structure results',
                1533666273
            );
        }
        $this->identifier = $identifier;
        $this->structure = $data['structure'];
        $this->structurePaths = $data['structurePaths'];
        $this->records = $data['records'];
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getStructure(): array
    {
        return $this->structure;
    }

    public function getRecords(): array
    {
        return $this->records;
    }

    public function findStructures(string $recordIdentifier, ?string $fieldName = ''): array
    {
        $structures = [];
        if (empty($this->structurePaths[$recordIdentifier])) {
            return $structures;
        }
        foreach ($this->structurePaths[$recordIdentifier] as $steps) {
            $structure = $this->structure;
            $steps[] = $recordIdentifier;
            if (!empty($fieldName)) {
                $steps[] = $fieldName;
            }
            foreach ($steps as $step) {
                if (!isset($structure[$step])) {
                    $structure = null;
                    break;
                }
                $structure = $structure[$step];
            }
            if (!empty($structure)) {
                $structures[implode('/', $steps)] = $structure;
            }
        }
        return $structures;
    }
}
