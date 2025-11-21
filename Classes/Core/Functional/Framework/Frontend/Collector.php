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

namespace TYPO3\TestingFramework\Core\Functional\Framework\Frontend;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsAllowedCallable;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

final class Collector implements SingletonInterface
{
    private array $tableFields;
    private array $structure = [];
    private array $structurePaths = [];
    private array $records = [];
    private ContentObjectRenderer $cObj;

    /**
     * This is called from UserContentObject via ContentObjectRenderer->callUserFunction()
     * for nested menu items - those use a USER content object for getDataAsJson().
     */
    public function setContentObjectRenderer(ContentObjectRenderer $cObj): void
    {
        $this->cObj = $cObj;
    }

    #[AsAllowedCallable]
    public function addRecordData($content, array $configuration, ServerRequestInterface $request): void
    {
        $recordIdentifier = $this->cObj->currentRecord;
        [$tableName] = explode(':', $recordIdentifier);
        $currentWatcherValue = $this->getCurrentWatcherValue($request);
        $position = strpos($currentWatcherValue, '/' . $recordIdentifier);

        $recordData = $this->filterFields($tableName, $this->cObj->data, $request);
        $this->records[$recordIdentifier] = $recordData;

        if ($currentWatcherValue === $recordIdentifier) {
            $this->structure[$recordIdentifier] = $recordData;
            $this->structurePaths[$recordIdentifier] = [[]];
        } elseif (!empty($position)) {
            $levelIdentifier = substr($currentWatcherValue, 0, $position);
            $this->addToStructure($levelIdentifier, $recordIdentifier, $recordData);
        }
    }

    #[AsAllowedCallable]
    public function addFileData($content, array $configuration, ServerRequestInterface $request): void
    {
        $currentFile = $this->cObj->getCurrentFile();

        if ($currentFile instanceof File) {
            $tableName = 'sys_file';
        } elseif ($currentFile instanceof FileReference) {
            $tableName = 'sys_file_reference';
        } else {
            return;
        }

        $recordData = $this->filterFields($tableName, $currentFile->getProperties(), $request);
        $recordIdentifier = $tableName . ':' . $currentFile->getUid();
        $this->records[$recordIdentifier] = $recordData;

        $currentWatcherValue = $this->getCurrentWatcherValue($request);
        $levelIdentifier = rtrim($currentWatcherValue, '/');
        $this->addToStructure($levelIdentifier, $recordIdentifier, $recordData);
    }

    #[AsAllowedCallable]
    public function attachSection(string $content, ?array $configuration = null): void
    {
        $section = [
            'structure' => $this->structure,
            'structurePaths' => $this->structurePaths,
            'records' => $this->records,
        ];

        $as = (!empty($configuration['as']) ? $configuration['as'] : null);
        $this->getRenderer()->addSection($section, $as);
        $this->reset();
    }

    private function filterFields(string $tableName, array $recordData, ServerRequestInterface $request): array
    {
        return array_intersect_key(
            $recordData,
            array_flip($this->getTableFields($tableName, $request))
        );
    }

    private function addToStructure($levelIdentifier, $recordIdentifier, array $recordData): void
    {
        $steps = explode('/', $levelIdentifier);
        $structurePaths = [];
        $structure = &$this->structure;

        foreach ($steps as $step) {
            [$identifier, $fieldName] = explode('.', $step);
            $structurePaths[] = $identifier;
            $structurePaths[] = $fieldName;
            if (!isset($structure[$identifier])) {
                return;
            }
            $structure = &$structure[$identifier];
            if (!isset($structure[$fieldName]) || !is_array($structure[$fieldName])) {
                $structure[$fieldName] = [];
            }
            $structure = &$structure[$fieldName];
        }

        $structure[$recordIdentifier] = $recordData;
        $this->structurePaths[$recordIdentifier][] = $structurePaths;
    }

    private function getTableFields(string $tableName, ServerRequestInterface $request): array
    {
        $typoScriptSetupArray = $request->getAttribute('frontend.typoscript')->getSetupArray();
        if (!isset($this->tableFields) && !empty($typoScriptSetupArray['config.']['watcher.']['tableFields.'])) {
            $this->tableFields = $typoScriptSetupArray['config.']['watcher.']['tableFields.'];
            foreach ($this->tableFields as &$fieldList) {
                $fieldList = GeneralUtility::trimExplode(',', $fieldList, true);
            }
            unset($fieldList);
        }

        return !empty($this->tableFields[$tableName]) ? $this->tableFields[$tableName] : [];
    }

    private function getCurrentWatcherValue(ServerRequestInterface $request): ?string
    {
        $registerStack = $request->getAttribute('frontend.register.stack');
        if ($registerStack !== null) {
            return $registerStack->current()->get('watcher');
        }
        // @deprecated: TYPO3 <v14 b/w compat. Remove $tsfe fallback and if clause above when v13 compat is removed.
        $tsfe = $request->getAttribute('frontend.controller');
        return $tsfe->register['watcher'] ?? null;
    }

    private function getRenderer(): Renderer
    {
        return GeneralUtility::makeInstance(Renderer::class);
    }

    /**
     * Collector needs to be reset after attaching a section, otherwise records will pile up.
     */
    private function reset(): void
    {
        $this->structure = [];
        $this->structurePaths = [];
        $this->records = [];
    }
}
