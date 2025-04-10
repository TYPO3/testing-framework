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

use Symfony\Component\Yaml\Yaml;

/**
 * Factory for DataHandler information parsed from a structured array
 * (or more specifically a scenario definition written in YAML)
 */
class DataHandlerFactory
{
    private const DYNAMIC_ID = 10000;

    private array $settings;

    /**
     * @var EntityConfiguration[]
     */
    private array $entityConfigurations = [];

    private array $dataMapPerWorkspace = [];
    private array $commandMapPerWorkspace = [];

    /**
     * @var bool[]
     */
    private array $suggestedIds = [];

    /**
     * @var int[]
     */
    private array $dynamicIdsPerEntity = [];

    /**
     * @var int[]
     */
    private array $staticIdsPerEntity = [];

    public static function fromYamlFile(string $yamlFile): self
    {
        return new static(Yaml::parseFile($yamlFile));
    }

    final public function __construct(array $settings)
    {
        $this->settings = $settings;
        $this->buildEntityConfigurations($settings['entitySettings'] ?? []);
        $this->processEntities($this->settings['entities'] ?? []);
    }

    public function getDataMapPerWorkspace(): array
    {
        return $this->dataMapPerWorkspace;
    }

    public function getCommandMapPerWorkspace(): array
    {
        return $this->commandMapPerWorkspace;
    }

    /**
     * @return string[]
     */
    public function getDataMapTableNames(): array
    {
        return array_unique(array_merge(
            [],
            ...array_map('array_keys', $this->dataMapPerWorkspace)
        ));
    }

    /**
     * @return bool[]
     */
    public function getSuggestedIds(): array
    {
        return $this->suggestedIds;
    }

    private function processEntities(
        array $settings,
        ?string $nodeId = null,
        ?string $parentId = null
    ): void {
        foreach ($settings as $entityName => $entitySettings) {
            $entityConfiguration = $this->provideEntityConfiguration($entityName);
            foreach ($entitySettings as $itemSettings) {
                $this->processEntityItem(
                    $entityConfiguration,
                    $itemSettings,
                    $nodeId,
                    $parentId
                );
            }
        }
    }

    private function processEntityItem(
        EntityConfiguration $entityConfiguration,
        array $itemSettings,
        ?string $nodeId = null,
        ?string $parentId = null
    ): void {
        $values = $this->processEntityValues(
            $entityConfiguration,
            $itemSettings,
            $nodeId,
            $parentId
        );
        $workspaceId = $itemSettings['version']['workspace'] ?? 0;
        $tableName = $entityConfiguration->getTableName();
        $newId = $this->getUniqueIdForNewRecords();
        $this->setInDataMap($tableName, $newId, $values, (int)$workspaceId);
        if (isset($itemSettings['actions'])) {
            $this->setInCommandMap($tableName, $newId, $nodeId, $itemSettings['actions'], (int)$workspaceId);
        }
        foreach ($itemSettings['versionVariants'] ?? [] as $versionVariantSettings) {
            $this->processVersionVariantItem(
                $entityConfiguration,
                $versionVariantSettings,
                $newId,
                $entityConfiguration->isNode() ? $newId : $nodeId
            );
        }
        foreach ($itemSettings['languageVariants'] ?? [] as $variantItemSettings) {
            $this->processLanguageVariantItem(
                $entityConfiguration,
                $variantItemSettings,
                [$newId],
                $entityConfiguration->isNode() ? '-' . $newId : $nodeId
            );
        }
        foreach ($itemSettings['children'] ?? [] as $childItemSettings) {
            $this->processEntityItem(
                $entityConfiguration,
                $childItemSettings,
                $nodeId,
                $newId
            );
        }
        if (!empty($itemSettings['entities']) && $entityConfiguration->isNode()) {
            $this->processEntities(
                $itemSettings['entities'],
                $newId,
                $parentId
            );
        }
    }

    private function processLanguageVariantItem(
        EntityConfiguration $entityConfiguration,
        array $itemSettings,
        array $ancestorIds,
        ?string $nodeId = null
    ): void {
        $values = $this->processEntityValues(
            $entityConfiguration,
            $itemSettings,
            $nodeId
        );
        // Language values can be overridden by declared values
        $values = array_merge(
            $entityConfiguration->processLanguageValues($ancestorIds),
            $values
        );
        $tableName = $entityConfiguration->getTableName();
        $newId = $this->getUniqueIdForNewRecords();
        $workspaceId = $itemSettings['version']['workspace'] ?? 0;
        $this->setInDataMap($tableName, $newId, $values, (int)$workspaceId);
        if (isset($itemSettings['actions'])) {
            $this->setInCommandMap($tableName, $newId, $nodeId, $itemSettings['actions'], (int)$workspaceId);
        }
        foreach ($itemSettings['versionVariants'] ?? [] as $versionVariantSettings) {
            $this->processVersionVariantItem(
                $entityConfiguration,
                $versionVariantSettings,
                $newId,
                $nodeId
            );
        }
        foreach ($itemSettings['languageVariants'] ?? [] as $variantItemSettings) {
            $this->processLanguageVariantItem(
                $entityConfiguration,
                $variantItemSettings,
                array_merge($ancestorIds, [$newId]),
                $nodeId
            );
        }
    }

    private function processVersionVariantItem(
        EntityConfiguration $entityConfiguration,
        array $itemSettings,
        string $ancestorId,
        ?string $nodeId = null
    ): void {
        if (isset($itemSettings['self'])) {
            throw new \LogicException(
                sprintf(
                    'Cannot declare "self" in version variant for entity "%s"',
                    $entityConfiguration->getName()
                ),
                1574365935
            );
        }
        if (isset($itemSettings['version']['id'])) {
            throw new \LogicException(
                sprintf(
                    'Cannot assign "id" for version variant for entity "%s"',
                    $entityConfiguration->getName()
                ),
                1574365936
            );
        }
        $values = $this->processEntityValues(
            $entityConfiguration,
            $itemSettings,
            $nodeId
        );
        $tableName = $entityConfiguration->getTableName();
        $this->setInDataMap($tableName, $ancestorId, $values, (int)$values['workspace']);
        if (isset($itemSettings['actions'])) {
            $this->setInCommandMap($tableName, $ancestorId, $nodeId, $itemSettings['actions'], (int)$values['workspace']);
        }
    }

    private function processEntityValues(
        EntityConfiguration $entityConfiguration,
        array $itemSettings,
        ?string $nodeId = null,
        ?string $parentId = null
    ): array {
        if (isset($itemSettings['self']) && isset($itemSettings['version'])) {
            throw new \LogicException(
                sprintf(
                    'Cannot declare both "self" and "version" for entity "%s"',
                    $entityConfiguration->getName()
                ),
                1534872399
            );
        }
        if (isset($itemSettings['version']) && empty($itemSettings['version']['workspace'])) {
            throw new \LogicException(
                sprintf(
                    'Cannot declare "version" without "workspace" for entity "%s"',
                    $entityConfiguration->getName()
                ),
                1534872400
            );
        }
        $sourceProperty = isset($itemSettings['version']) ? 'version' : 'self';
        if (empty($itemSettings[$sourceProperty]) || !is_array($itemSettings[$sourceProperty])) {
            throw new \LogicException(
                sprintf(
                    'Missing "%s" declaration for entity "%s"',
                    $sourceProperty,
                    $entityConfiguration->getName()
                ),
                1533734369
            );
        }
        $staticId = (int)($itemSettings[$sourceProperty]['id'] ?? 0);
        if ($this->hasStaticId($entityConfiguration, $staticId)) {
            throw new \LogicException(
                sprintf(
                    'Cannot assign ID "%s" multiple times',
                    $staticId
                ),
                1533734370
            );
        }
        $parentColumnName = $entityConfiguration->getParentColumnName();
        $nodeColumnName = $entityConfiguration->getNodeColumnName();
        // @todo probably dynamic assignment is a bad idea & we should just use auto incremented values...
        $incrementValue = !empty($itemSettings['version']) ? 2 : 1;
        if ($staticId > 0) {
            $suggestedId = $staticId;
            $this->incrementDynamicId($entityConfiguration, $incrementValue - 1);
        } else {
            $suggestedId = $this->incrementDynamicId($entityConfiguration, $incrementValue);
        }
        $this->addSuggestedId($entityConfiguration, $suggestedId);
        $values = $entityConfiguration->processValues($itemSettings[$sourceProperty]);
        $values['uid'] = $suggestedId;
        // Assign node pointer value
        if ($nodeId !== null && !empty($nodeColumnName)) {
            $values[$nodeColumnName] = $nodeId;
        }
        // Assign parent pointer value
        if ($parentId !== null && !empty($parentColumnName)) {
            $values[$parentColumnName] = $parentId;
        }
        return $values;
    }

    private function buildEntityConfigurations(array $settings): void
    {
        $defaultSettings = $settings['*'] ?? [];
        foreach ($settings as $entityName => $entitySettings) {
            if ($entityName === '*') {
                continue;
            }
            $entityConfiguration = EntityConfiguration::fromArray(
                $entityName,
                array_merge_recursive(
                    $defaultSettings,
                    $entitySettings
                )
            );
            $this->entityConfigurations[$entityName] = $entityConfiguration;
        }
    }

    private function provideEntityConfiguration(
        string $entityName
    ): EntityConfiguration {
        if (empty($this->entityConfigurations[$entityName])) {
            $this->entityConfigurations[$entityName] = new EntityConfiguration($entityName);
        }
        return $this->entityConfigurations[$entityName];
    }

    private function addSuggestedId(
        EntityConfiguration $entityConfiguration,
        int $suggestedId
    ): void {
        $identifier = $entityConfiguration->getTableName() . ':' . $suggestedId;
        if (isset($this->suggestedIds[$identifier])) {
            throw new \LogicException(
                sprintf(
                    'Cannot redeclare identifier "%s" with "%d"',
                    $identifier,
                    $suggestedId
                ),
                1568146788
            );
        }
        $this->suggestedIds[$identifier] = true;
    }

    private function hasStaticId(
        EntityConfiguration $entityConfiguration,
        int $id
    ): bool {
        return in_array(
            $id,
            $this->staticIdsPerEntity[$entityConfiguration->getName()] ?? [],
            true
        );
    }

    private function incrementDynamicId(
        EntityConfiguration $entityConfiguration,
        int $incrementValue = 1
    ): int {
        if (!isset($this->dynamicIdsPerEntity[$entityConfiguration->getName()])) {
            $this->dynamicIdsPerEntity[$entityConfiguration->getName()] = self::DYNAMIC_ID;
        }
        $result = $this->dynamicIdsPerEntity[$entityConfiguration->getName()];
        // increment for next(!) assignment, since current process might create version or language variants
        $this->dynamicIdsPerEntity[$entityConfiguration->getName()] += $incrementValue;
        return $result;
    }

    /**
     * Adds values to data map and ensures sorting.
     * Per default DataHandler inserts records to top on according page
     * however, this factory shall insert sequentially one after another.
     */
    private function setInDataMap(
        string $tableName,
        string $identifier,
        array $values,
        ?int $workspaceId = 0
    ): void {
        if (empty($values)) {
            $this->dataMapPerWorkspace[$workspaceId][$tableName][$identifier] = $values;
            return;
        }
        $tableDataMap = $this->filterDataMapByPageId(
            $workspaceId,
            $tableName,
            $values['pid'] ?? null
        );
        $identifiers = array_keys($tableDataMap);
        $currentIndex = array_search($identifier, $identifiers);
        // current item did not have any values in data map, use last identifer
        if ($currentIndex === false && !empty($identifiers)) {
            $values['pid'] = '-' . $identifiers[count($identifiers) - 1];
            // current item does have values in data map, use previous identifier
        } elseif ($currentIndex > 0) {
            $previousIndex = $identifiers[$currentIndex - 1];
            $values['pid'] = '-' . $identifiers[$previousIndex];
        }
        $this->dataMapPerWorkspace[$workspaceId][$tableName][$identifier] = $values;
    }

    private function setInCommandMap(
        string $tableName,
        string $identifier,
        ?string $nodeId,
        array $actionItems,
        ?int $workspaceId = 0
    ): void {
        if (empty($actionItems)) {
            return;
        }
        // @todo implement `immediate` actions -> needs to split dataMap & commandMap in logical sections
        foreach ($actionItems as $actionItem) {
            $action = $actionItem['action'] ?? null;
            $type = $actionItem['type'] ?? null;
            $target = $actionItem['target'] ?? null;
            if ($action === 'move') {
                if ($type === 'toPage' && $target !== null) {
                    $this->commandMapPerWorkspace[$workspaceId][$tableName][$identifier]['move'] = $target;
                } elseif ($type === 'toTop' && $nodeId !== null) {
                    $this->commandMapPerWorkspace[$workspaceId][$tableName][$identifier]['move'] = $nodeId;
                } elseif ($type === 'afterRecord' && $target !== null) {
                    $this->commandMapPerWorkspace[$workspaceId][$tableName][$identifier]['move'] = '-' . $target;
                }
            } elseif ($action === 'delete') {
                $this->commandMapPerWorkspace[$workspaceId][$tableName][$identifier]['delete'] = true;
            } elseif ($action === 'discard' && $workspaceId > 0) {
                $this->commandMapPerWorkspace[$workspaceId][$tableName][$identifier]['clearWSID'] = true;
            }
        }
    }

    private function filterDataMapByPageId(
        int $workspaceId,
        string $tableName,
        int|string|null $pageId = null
    ): array {
        if ($pageId === null) {
            return [];
        }
        return array_filter(
            $this->dataMapPerWorkspace[$workspaceId][$tableName] ?? [],
            function (array $item) use ($pageId, $workspaceId) {
                $itemPageId = $this->resolveDataMapPageId(
                    $workspaceId,
                    $item['pid'] ?? null
                );
                return $itemPageId === $pageId;
            }
        );
    }

    private function resolveDataMapPageId(int $workspaceId, int|string|null $pageId = null): int|string|null
    {
        $normalizePageId = (string)$pageId;
        if ($pageId === null || $normalizePageId[0] !== '-') {
            return $pageId;
        }

        $regularPageId = substr($normalizePageId, 1);
        $resolvedPageId = $this->dataMapPerWorkspace[$workspaceId]['pages'][$regularPageId]['pid'] ?? null;
        return $this->resolveDataMapPageId($workspaceId, $resolvedPageId);
    }

    /**
     * This function generates a unique id by using the more entropy parameter, so it can be used in DataHandler.
     */
    private function getUniqueIdForNewRecords(): string
    {
        return str_replace('.', '', uniqid('NEW', true));
    }
}
