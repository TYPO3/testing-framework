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

class DataHandlerWriter
{
    /**
     * @var DataHandler
     */
    private $dataHandler;

    /**
     * @var BackendUserAuthentication
     */
    private $backendUser;

    /**
     * @var string[]
     */
    private $errors = [];

    /**
     * @param BackendUserAuthentication $backendUser
     * @return static
     */
    public static function withBackendUser(
        BackendUserAuthentication $backendUser
    ): self {
        $dataHandler = new DataHandler();
        if (isset($backendUser->uc['copyLevels'])) {
            $dataHandler->copyTree = $backendUser->uc['copyLevels'];
        }
        $target = new static($dataHandler, $backendUser);
        return $target;
    }

    /**
     * @param DataHandler $dataHandler
     * @param BackendUserAuthentication $backendUser
     */
    public function __construct(
        DataHandler $dataHandler,
        BackendUserAuthentication $backendUser
    ) {
        $this->dataHandler = $dataHandler;
        $this->backendUser = $backendUser;
    }

    /**
     * @param DataHandlerFactory $factory
     */
    public function invokeFactory(DataHandlerFactory $factory)
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

    /**
     * @param array $dataMap
     * @return array
     */
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
                        if (strpos($value, 'NEW') === 0) {
                            return $this->dataHandler->substNEWwithIDs[$value] ?? $value;
                        }
                        if (strpos($value, '-NEW') === 0) {
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

    /**
     * @param array $commandMap
     * @return array
     */
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
                        if (strpos($value, 'NEW') === 0) {
                            return $this->dataHandler->substNEWwithIDs[$value] ?? $value;
                        }
                        if (strpos($value, '-NEW') === 0) {
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
