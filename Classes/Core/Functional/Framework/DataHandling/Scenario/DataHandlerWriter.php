<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Scenario;

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

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DataHandlerWriter
{
    private array $errors = [];

    final public function __construct(
        private readonly DataHandler $dataHandler,
        private readonly BackendUserAuthentication $backendUser,
    ) {}

    public static function withBackendUser(BackendUserAuthentication $backendUser): self
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        if (isset($backendUser->uc['copyLevels']) && property_exists($dataHandler, 'copyTree')) {
            $dataHandler->copyTree = $backendUser->uc['copyLevels'];
        }
        return new static($dataHandler, $backendUser);
    }

    public function invokeFactory(DataHandlerFactory $factory): void
    {
        $this->dataHandler->suggestedInsertUids = $factory->getSuggestedIds();
        foreach ($factory->getDataMapPerWorkspace() as $workspaceId => $dataMap) {
            $dataMap = $this->updateDataMap($dataMap);
            $backendUser = clone $this->backendUser;
            $backendUser->workspace = $workspaceId;
            $this->dataHandler->start($dataMap, [], $backendUser);
            $this->dataHandler->process_datamap();
            $this->errors = array_merge(
                $this->errors,
                $this->dataHandler->errorLog
            );
        }
        foreach ($factory->getCommandMapPerWorkspace() as $workspaceId => $commandMap) {
            $commandMap = $this->updateCommandMap($commandMap);
            $backendUser = clone $this->backendUser;
            $backendUser->workspace = $workspaceId;
            $this->dataHandler->start([], $commandMap, $backendUser);
            $this->dataHandler->process_cmdmap();
            $this->errors = array_merge(
                $this->errors,
                $this->dataHandler->errorLog
            );
        }
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    private function updateDataMap(array $dataMap): array
    {
        $updatedTableDataMap = [];
        foreach ($dataMap as $tableName => $tableDataMap) {
            foreach ($tableDataMap as $key => $values) {
                $key = $this->dataHandler->substNEWwithIDs[$key] ?? $key;
                $values = array_map(
                    function ($value) {
                        if (!is_string($value)) {
                            return $value;
                        }
                        if (str_starts_with($value, 'NEW')) {
                            return $this->dataHandler->substNEWwithIDs[$value] ?? $value;
                        }
                        if (str_starts_with($value, '-NEW')) {
                            return $this->dataHandler->substNEWwithIDs[substr($value, 1)] ?? $value;
                        }
                        return $value;
                    },
                    $values
                );
                if ((string)$key === (string)(int)$key) {
                    unset($values['pid']);
                }
                $updatedTableDataMap[$tableName][$key] = $values;
            }
        }
        return $updatedTableDataMap;
    }

    private function updateCommandMap(array $commandMap): array
    {
        $updatedTableCommandMap = [];
        foreach ($commandMap as $tableName => $tableDataMap) {
            foreach ($tableDataMap as $key => $values) {
                $key = $this->dataHandler->substNEWwithIDs[$key] ?? $key;
                $values = array_map(
                    function ($value) {
                        if (!is_string($value)) {
                            return $value;
                        }
                        if (str_starts_with($value, 'NEW')) {
                            return $this->dataHandler->substNEWwithIDs[$value] ?? $value;
                        }
                        if (str_starts_with($value, '-NEW')) {
                            return $this->dataHandler->substNEWwithIDs[substr($value, 1)] ?? $value;
                        }
                        return $value;
                    },
                    $values
                );
                $updatedTableCommandMap[$tableName][$key] = $values;
            }
        }
        return $updatedTableCommandMap;
    }
}
