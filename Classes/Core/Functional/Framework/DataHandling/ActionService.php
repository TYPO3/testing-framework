<?php
declare(strict_types=1);
namespace TYPO3\TestingFramework\Core\Functional\Framework\DataHandling;

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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Workspaces\Service\WorkspaceService;
use TYPO3\TestingFramework\Core\Exception;

/**
 * DataHandler Actions
 */
class ActionService
{
    /**
     * @var DataHandler
     */
    protected $dataHandler;

    /**
     * @return DataHandler
     */
    public function getDataHandler(): DataHandler
    {
        return $this->dataHandler;
    }

    /**
     * Creates the new record and returns an array keyed by table, containing the new id
     *
     * @param string $tableName
     * @param int $pageId
     * @param array $recordData
     * @return array
     */
    public function createNewRecord(string $tableName, int $pageId, array $recordData): array
    {
        return $this->createNewRecords($pageId, [$tableName => $recordData]);
    }

    /**
     * Creates the records and returns an array keyed by table, containing the new ids
     *
     * @param int $pageId
     * @param array $tableRecordData
     * @return array
     */
    public function createNewRecords(int $pageId, array $tableRecordData): array
    {
        $dataMap = [];
        $newTableIds = [];
        $currentUid = null;
        $previousTableName = null;
        $previousUid = null;
        foreach ($tableRecordData as $tableName => $recordData) {
            $recordData = $this->resolvePreviousUid($recordData, $currentUid);
            if (!isset($recordData['pid'])) {
                $recordData['pid'] = $pageId;
            }
            $currentUid = StringUtility::getUniqueId('NEW');
            $newTableIds[$tableName][] = $currentUid;
            $dataMap[$tableName][$currentUid] = $recordData;
            if ($previousTableName !== null && $previousUid !== null) {
                $dataMap[$previousTableName][$previousUid] = $this->resolveNextUid(
                    $dataMap[$previousTableName][$previousUid],
                    $currentUid
                );
            }
            $previousTableName = $tableName;
            $previousUid = $currentUid;
        }
        $this->createDataHandler();
        $this->dataHandler->start($dataMap, []);
        $this->dataHandler->process_datamap();

        foreach ($newTableIds as $tableName => &$ids) {
            foreach ($ids as &$id) {
                if (!empty($this->dataHandler->substNEWwithIDs[$id])) {
                    $id = $this->dataHandler->substNEWwithIDs[$id];
                }
            }
        }

        return $newTableIds;
    }

    /**
     * @param string $tableName
     * @param int $uid
     * @param array $recordData
     * @param array $deleteTableRecordIds
     */
    public function modifyRecord(string $tableName, int $uid, array $recordData, array $deleteTableRecordIds = null)
    {
        $dataMap = [
            $tableName => [
                $uid => $recordData,
            ],
        ];
        $commandMap = [];
        if (!empty($deleteTableRecordIds)) {
            foreach ($deleteTableRecordIds as $tableName => $recordIds) {
                foreach ($recordIds as $recordId) {
                    $commandMap[$tableName][$recordId]['delete'] = true;
                }
            }
        }
        $this->createDataHandler();
        $this->dataHandler->start($dataMap, $commandMap);
        $this->dataHandler->process_datamap();
        if (!empty($commandMap)) {
            $this->dataHandler->process_cmdmap();
        }
    }

    /**
     * @param int $pageId
     * @param array $tableRecordData
     */
    public function modifyRecords(int $pageId, array $tableRecordData)
    {
        $dataMap = [];
        $currentUid = null;
        $previousTableName = null;
        $previousUid = null;
        foreach ($tableRecordData as $tableName => $recordData) {
            if (empty($recordData['uid'])) {
                continue;
            }
            $recordData = $this->resolvePreviousUid($recordData, $currentUid);
            $currentUid = $recordData['uid'];
            if ($recordData['uid'] === '__NEW') {
                $currentUid = StringUtility::getUniqueId('NEW');
            }
            if (strpos((string)$currentUid, 'NEW') === 0) {
                $recordData['pid'] = $pageId;
            }
            unset($recordData['uid']);
            $dataMap[$tableName][$currentUid] = $recordData;
            if ($previousTableName !== null && $previousUid !== null) {
                $dataMap[$previousTableName][$previousUid] = $this->resolveNextUid(
                    $dataMap[$previousTableName][$previousUid],
                    $currentUid
                );
            }
            $previousTableName = $tableName;
            $previousUid = $currentUid;
        }
        $this->createDataHandler();
        $this->dataHandler->start($dataMap, []);
        $this->dataHandler->process_datamap();
    }

    /**
     * @param string $tableName
     * @param int $uid
     * @return array
     */
    public function deleteRecord(string $tableName, int $uid): array
    {
        return $this->deleteRecords(
            [
                $tableName => [$uid],
            ]
        );
    }

    /**
     * @param array $tableRecordIds
     * @return array
     */
    public function deleteRecords(array $tableRecordIds): array
    {
        $commandMap = [];
        foreach ($tableRecordIds as $tableName => $ids) {
            foreach ($ids as $uid) {
                $commandMap[$tableName][$uid] = [
                    'delete' => true,
                ];
            }
        }
        $this->createDataHandler();
        $this->dataHandler->start([], $commandMap);
        $this->dataHandler->process_cmdmap();
        // Deleting workspace records is actually a copy(!)
        return $this->dataHandler->copyMappingArray;
    }

    /**
     * @param string $tableName
     * @param int $uid
     */
    public function clearWorkspaceRecord(string $tableName, int $uid)
    {
        $this->clearWorkspaceRecords(
            [
                $tableName => [$uid],
            ]
        );
    }

    /**
     * @param array $tableRecordIds
     */
    public function clearWorkspaceRecords(array $tableRecordIds)
    {
        $commandMap = [];
        foreach ($tableRecordIds as $tableName => $ids) {
            foreach ($ids as $uid) {
                $commandMap[$tableName][$uid] = [
                    'version' => [
                        'action' => 'clearWSID',
                    ]
                ];
            }
        }
        $this->createDataHandler();
        $this->dataHandler->start([], $commandMap);
        $this->dataHandler->process_cmdmap();
    }

    /**
     * @param string $tableName
     * @param int $uid
     * @param int $pageId
     * @param array $recordData
     * @return array
     */
    public function copyRecord(string $tableName, int $uid, int $pageId, array $recordData = null): array
    {
        $commandMap = [
            $tableName => [
                $uid => [
                    'copy' => $pageId,
                ],
            ],
        ];
        if ($recordData !== null) {
            $commandMap[$tableName][$uid]['copy'] = [
                'action' => 'paste',
                'target' => $pageId,
                'update' => $recordData,
            ];
        }
        $this->createDataHandler();
        $this->dataHandler->start([], $commandMap);
        $this->dataHandler->process_cmdmap();
        return $this->dataHandler->copyMappingArray;
    }

    /**
     * @param string $tableName
     * @param int $uid uid of the record you want to move
     * @param int $targetUid target uid of a page or record. if positive, means it's PID where the record will be moved into,
     * negative means record will be placed after record with this uid. In this case it's uid of the record from
     * the same table, and not a PID.
     * @param array $recordData
     * @return array
     */
    public function moveRecord(string $tableName, int $uid, int $targetUid, array $recordData = null): array
    {
        $commandMap = [
            $tableName => [
                $uid => [
                    'move' => $targetUid,
                ],
            ],
        ];
        if ($recordData !== null) {
            $commandMap[$tableName][$uid]['move'] = [
                'action' => 'paste',
                'target' => $targetUid,
                'update' => $recordData,
            ];
        }
        $this->createDataHandler();
        $this->dataHandler->start([], $commandMap);
        $this->dataHandler->process_cmdmap();
        return $this->dataHandler->copyMappingArray;
    }

    /**
     * @param string $tableName
     * @param int $uid
     * @param int $languageId
     * @return array
     */
    public function localizeRecord(string $tableName, int $uid, int $languageId): array
    {
        $commandMap = [
            $tableName => [
                $uid => [
                    'localize' => $languageId,
                ],
            ],
        ];
        $this->createDataHandler();
        $this->dataHandler->start([], $commandMap);
        $this->dataHandler->process_cmdmap();
        return $this->dataHandler->copyMappingArray;
    }

    /**
     * @param string $tableName
     * @param int $uid
     * @param int $languageId
     * @return array
     */
    public function copyRecordToLanguage(string $tableName, int $uid, int $languageId): array
    {
        $commandMap = [
            $tableName => [
                $uid => [
                    'copyToLanguage' => $languageId,
                ],
            ],
        ];
        $this->createDataHandler();
        $this->dataHandler->start([], $commandMap);
        $this->dataHandler->process_cmdmap();
        return $this->dataHandler->copyMappingArray;
    }

    /**
     * @param string $tableName
     * @param int $uid
     * @param string $fieldName
     * @param array $referenceIds
     */
    public function modifyReferences(string $tableName, int $uid, string $fieldName, array $referenceIds)
    {
        $dataMap = [
            $tableName => [
                $uid => [
                    $fieldName => implode(',', $referenceIds),
                ],
            ]
        ];
        $this->createDataHandler();
        $this->dataHandler->start($dataMap, []);
        $this->dataHandler->process_datamap();
    }

    /**
     * @param string $tableName
     * @param int $liveUid
     * @param bool $throwException
     */
    public function publishRecord(string $tableName, $liveUid, bool $throwException = true)
    {
        $this->publishRecords([$tableName => [$liveUid]], $throwException);
    }

    /**
     * @param array $tableLiveUids
     * @param bool $throwException
     * @throws Exception
     */
    public function publishRecords(array $tableLiveUids, bool $throwException = true)
    {
        $commandMap = [];
        foreach ($tableLiveUids as $tableName => $liveUids) {
            foreach ($liveUids as $liveUid) {
                $versionedUid = $this->getVersionedId($tableName, $liveUid);
                if (empty($versionedUid)) {
                    if ($throwException) {
                        throw new Exception('Versioned UID could not be determined', 1476049592);
                    } else {
                        continue;
                    }
                }

                $commandMap[$tableName][$liveUid] = [
                    'version' => [
                        'action' => 'swap',
                        'swapWith' => $versionedUid,
                        'notificationAlternativeRecipients' => [],
                    ],
                ];
            }
        }
        $this->createDataHandler();
        $this->dataHandler->start([], $commandMap);
        $this->dataHandler->process_cmdmap();
    }

    /**
     * @param int $workspaceId
     */
    public function publishWorkspace(int $workspaceId)
    {
        $commandMap = $this->getWorkspaceService()->getCmdArrayForPublishWS($workspaceId, false);
        $this->createDataHandler();
        $this->dataHandler->start([], $commandMap);
        $this->dataHandler->process_cmdmap();
    }

    /**
     * @param int $workspaceId
     */
    public function swapWorkspace(int $workspaceId)
    {
        $commandMap = $this->getWorkspaceService()->getCmdArrayForPublishWS($workspaceId, true);
        $this->createDataHandler();
        $this->dataHandler->start([], $commandMap);
        $this->dataHandler->process_cmdmap();
    }

    /**
     * @param array $dataMap
     * @param array $commandMap
     */
    public function invoke(array $dataMap, array $commandMap, array $suggestedIds = [])
    {
        $this->createDataHandler();
        $this->dataHandler->suggestedInsertUids = $suggestedIds;
        $this->dataHandler->start($dataMap, $commandMap);
        $this->dataHandler->process_datamap();
        $this->dataHandler->process_cmdmap();
    }

    /**
     * @param array $recordData
     * @param NULL|string|int $previousUid
     * @return array
     */
    protected function resolvePreviousUid(array $recordData, $previousUid): array
    {
        if ($previousUid === null) {
            return $recordData;
        }
        foreach ($recordData as $fieldName => $fieldValue) {
            if (strpos((string)$fieldValue, '__previousUid') === false) {
                continue;
            }
            $recordData[$fieldName] = str_replace('__previousUid', $previousUid, $fieldValue);
        }
        return $recordData;
    }

    /**
     * @param array $recordData
     * @param NULL|string|int $nextUid
     * @return array
     */
    protected function resolveNextUid(array $recordData, $nextUid): array
    {
        if ($nextUid === null) {
            return $recordData;
        }
        foreach ($recordData as $fieldName => $fieldValue) {
            if (strpos((string)$fieldValue, '__nextUid') === false) {
                continue;
            }
            $recordData[$fieldName] = str_replace('__nextUid', $nextUid, $fieldValue);
        }
        return $recordData;
    }

    /**
     * @param string $tableName
     * @param int|string $liveUid
     * @return NULL|int
     */
    protected function getVersionedId(string $tableName, $liveUid)
    {
        $versionedId = null;
        $liveUid = (int)$liveUid;
        $workspaceId = (int)$this->getBackendUser()->workspace;

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();
        $statement = $queryBuilder
            ->select('uid')
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter(-1, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    't3ver_oid',
                    $queryBuilder->createNamedParameter($liveUid, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    't3ver_wsid',
                    $queryBuilder->createNamedParameter($workspaceId, \PDO::PARAM_INT)
                )
            )
            ->execute();

        $row = $statement->fetch();
        if (!empty($row['uid'])) {
            $versionedId = (int)$row['uid'];
        }
        return $versionedId;
    }

    /**
     * @return DataHandler
     */
    protected function createDataHandler(): DataHandler
    {
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $backendUser = $this->getBackendUser();
        if (isset($backendUser->uc['copyLevels'])) {
            $this->dataHandler->copyTree = $backendUser->uc['copyLevels'];
        }
        return $this->dataHandler;
    }

    /**
     * @return WorkspaceService
     */
    protected function getWorkspaceService(): WorkspaceService
    {
        return GeneralUtility::makeInstance(
            WorkspaceService::class
        );
    }

    /**
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
