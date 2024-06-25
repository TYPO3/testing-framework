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

use TYPO3\CMS\Core\SingletonInterface;

final class Parser implements SingletonInterface
{
    private array $paths = [];
    private array $records = [];

    public function getPaths(): array
    {
        return $this->paths;
    }

    public function getRecords(): array
    {
        return $this->records;
    }

    public function parse(array $structure): void
    {
        $this->process($structure);
    }

    private function process(array $iterator, array $path = []): void
    {
        foreach ($iterator as $identifier => $properties) {
            if (!is_array($properties)) {
                continue;
            }
            $this->addRecord($identifier, $properties);
            $this->addPath($identifier, $path);
            foreach ($properties as $propertyName => $propertyValue) {
                if (!is_array($propertyValue)) {
                    continue;
                }
                $nestedPath = array_merge($path, [$identifier, $propertyName]);
                $this->process($propertyValue, $nestedPath);
            }
        }
    }

    private function addRecord(string $identifier, array $properties): void
    {
        if (isset($this->records[$identifier])) {
            return;
        }
        foreach ($properties as $propertyName => $propertyValue) {
            if (is_array($propertyValue)) {
                unset($properties[$propertyName]);
            }
        }
        $this->records[$identifier] = $properties;
    }

    private function addPath(string $identifier, array $path): void
    {
        if (!isset($this->paths[$identifier])) {
            $this->paths[$identifier] = [];
        }
        $this->paths[$identifier][] = $path;
    }
}
