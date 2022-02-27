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
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Workspaces\Service\WorkspaceService;
use TYPO3\TestingFramework\Core\Exception;

/**
 * This is a helper to run DataHandler actions in tests.
 *
 * It is primarily used in core functional tests to execute DataHandler actions.
 * Hundreds of core tests use this. A typical use case:
 *
 * 1. Load a fixture data set into database - for instance some pages and content elements
 * 2. Run a DataHandler action like "localize this content element" using localizeRecord() below
 * 3. Verify resulting database state by comparing with a target fixture
 */
class ActionService
{
    /**
     * @var DataHandler
     */
    protected $dataHandler;

    /**
     * A low level method to retrieve the executing DataHandler method after
     * actions have been performed. Usually only used when "arbitrary" commands
     * are run via invoke(), and / or some special DataHandler state is checked
     * after some operation.
     */
    public function getDataHandler(): DataHandler
    {
        return $this->dataHandler;
    }

    /**
     * Creates a new record and returns an array keyed by table, containing the new id.
     */
    public function createNewRecord(string $tableName, int $pageId, array $recordData): array
    {
        return $this->createNewRecords($pageId, [$tableName => $recordData]);
    }

    /**
     * Creates the records and returns an array keyed by table, containing the new ids.
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
            $currentUid = $this->getUniqueIdForNewRecords();
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
     * Modify an existing record.
     *
     * Example:
     * modifyRecord('tt_content', 42, ['hidden' => '1']); // Modify a single record
     * modifyRecord('tt_content', 42, ['hidden' => '1'], ['tx_irre_table' => [4]]); // Modify a record and delete a child
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
     * Modify multiple records on a single page.
     *
     * Example:
     * modifyRecords(
     *      $pageUid,
     *      [
     *          'tt_content' => [
     *              'uid' => 3,
     *              'header' => 'Testing #1',
     *              'tx_irre_hotel' => 5
     *              self::FIELD_Categories => $categoryNewId,
     *      ],
     *      // ... another record on this page
     * );
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
                $currentUid = $this->getUniqueIdForNewRecords();
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
     * Delete single record. Typically sets deleted=1 for soft-delete aware tables.
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
     * Delete multiple records in many tables.
     *
     * Example:
     * deleteRecords([
     *      'tt_content' => [300, 301, 302],
     *      'other_table' => [42],
     * ]);
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
     * Discard a single workspace record.
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
     * Discard multiple workspace records.
     *
     * Example:
     * clearWorkspaceRecords([
     *      'tt_content' => [ 5, 7 ],
     *      ...
     * ]);
     */
    public function clearWorkspaceRecords(array $tableRecordIds)
    {
        $commandMap = [];
        foreach ($tableRecordIds as $tableName => $ids) {
            foreach ($ids as $uid) {
                $commandMap[$tableName][$uid] = [
                    'version' => [
                        'action' => 'clearWSID',
                    ],
                ];
            }
        }
        $this->createDataHandler();
        $this->dataHandler->start([], $commandMap);
        $this->dataHandler->process_cmdmap();
    }

    /**
     * Copy a record to a different page. Optionally change data of inserted record.
     *
     * Example:
     * copyRecord('tt_content', 42, 5, ['header' => 'Testing #1']);
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
     * Move a record to a different position or page. Optionally change the moved record.
     *
     * Example:
     * moveRecord('tt_content', 42, -5, ['hidden' => '1']);
     *
     * @param string $tableName
     * @param int $uid uid of the record to move
     * @param int $targetUid target uid of a page or record. if positive, means it's PID where the record will be moved into,
*                 negative means record will be placed after record with this uid. In this case it's uid of the record from
     *            the same table, and not a PID.
     * @param array $recordData Additional record data to change when moving.
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
     * Localize a single record so some target language id.
     * This is the "translate" operation from the page module for a single record, where l10n_parent
     * is set to the default language record and l10n_source to the id of the source record.
     */
    public function localizeRecord(string $tableName, int $uid, int $languageId): array
    {
        return $this->localizeRecords($languageId, [$tableName => [$uid]]);
    }

    /**
     * Localize multiple records to some target language id.
     *
     * Example:
     * localizeRecords(self::VALUE_LanguageId, [
     *      'tt_content' => [ 45, 87 ],
     * ]);
     *
     * @return array An array of new ids ['tt_content'][45] = theNewUid;
     */
    public function localizeRecords(int $languageId, array $tableRecordIds): array
    {
        $commandMap = [];
        foreach ($tableRecordIds as $tableName => $ids) {
            foreach ($ids as $uid) {
                $commandMap[$tableName][$uid] = [
                    'localize' => $languageId,
                ];
            }
        }
        $this->createDataHandler();
        $this->dataHandler->start([], $commandMap);
        $this->dataHandler->process_cmdmap();
        return $this->dataHandler->copyMappingArray_merged;
    }

    /**
     * Copy a single record to some target language id.
     * This is the "copy" operation from the page module for a single record, where l10n_parent
     * of the copied record is 0.
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
     * Update relations of a record to a new set of relations.
     *
     * Example:
     * modifyReferences(
     *      'tt_content',
     *      42,
     *      tx_irre_hotels,
     *      [ 3, 5, 7 ]
     * );
     */
    public function modifyReferences(string $tableName, int $uid, string $fieldName, array $referenceIds)
    {
        $dataMap = [
            $tableName => [
                $uid => [
                    $fieldName => implode(',', $referenceIds),
                ],
            ],
        ];
        $this->createDataHandler();
        $this->dataHandler->start($dataMap, []);
        $this->dataHandler->process_datamap();
    }

    /**
     * Publish a single workspace record. Use the live record id of the workspace record.
     */
    public function publishRecord(string $tableName, $liveUid, bool $throwException = true)
    {
        $this->publishRecords([$tableName => [$liveUid]], $throwException);
    }

    /**
     * Publish multiple records to live.
     *
     * Example:
     * publishRecords([
     *      'tt_content' => [ 42, 87 ],
     *      ...
     * ]
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
                    }
                    continue;
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
     * Publish all records of an entire workspace.
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
     * @deprecated: Will be removed with next major version, workspace swap has been dropped with core v11.
     */
    public function swapWorkspace(int $workspaceId)
    {
        $commandMap = $this->getWorkspaceService()->getCmdArrayForPublishWS($workspaceId, true);
        $this->createDataHandler();
        $this->dataHandler->start([], $commandMap);
        $this->dataHandler->process_cmdmap();
    }

    /**
     * A low level method to invoke an arbitrary DataHandler data and / or command map.
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
     * @param string|int|null $previousUid
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
     * @param string|int|null $nextUid
     * @return array
     */
    protected function resolveNextUid(array $recordData, $nextUid): array
    {
        if ($nextUid === null) {
            return $recordData;
        }
        foreach ($recordData as $fieldName => $fieldValue) {
            if (is_array($fieldValue) || strpos((string)$fieldValue, '__nextUid') === false) {
                continue;
            }
            $recordData[$fieldName] = str_replace('__nextUid', $nextUid, $fieldValue);
        }
        return $recordData;
    }

    /**
     * @param string $tableName
     * @param int|string $liveUid
     * @return int|null
     */
    protected function getVersionedId(string $tableName, $liveUid)
    {
        $versionedId = null;
        $liveUid = (int)$liveUid;
        $workspaceId = (int)$this->getBackendUser()->workspace;

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder
            ->select('uid')
            ->from($tableName)
            ->where(
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
        if ((new Typo3Version())->getMajorVersion() >= 11) {
            $row = $statement->fetchAssociative();
        } else {
            // @deprecated: Will be removed with next major version - core v10 compat.
            $row = $statement->fetch();
        }
        if (!empty($row['uid'])) {
            return (int)$row['uid'];
        }

        // Check if the actual record is a new record created in the draft workspace
        // which contains the state of t3ver_state=1, so we verify this by re-fetching the record
        // from the database
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder
            ->select('uid')
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($liveUid, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    't3ver_wsid',
                    $queryBuilder->createNamedParameter($workspaceId, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    't3ver_oid',
                    $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    't3ver_state',
                    $queryBuilder->createNamedParameter(1, \PDO::PARAM_INT)
                )
            )
            ->execute();
        if ((new Typo3Version())->getMajorVersion() >= 11) {
            $row = $statement->fetchAssociative();
        } else {
            // @deprecated: Will be removed with next major version - core v10 compat.
            $row = $statement->fetch();
        }
        if (!empty($row)) {
            // This is effectively the same record as $liveUid, but only if the constraints from above match
            return (int)$row['uid'];
        }
        return null;
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
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * This function generates a unique id by using the more entropy parameter, so it can be used
     * in DataHandler.
     *
     * @return string
     */
    private function getUniqueIdForNewRecords(): string
    {
        return str_replace('.', '', uniqid('NEW', true));
    }
}
