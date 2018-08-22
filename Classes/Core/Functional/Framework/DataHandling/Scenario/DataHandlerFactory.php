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
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Factory for DataHandler information parsed from a structured array
 * (or more specifically a scenario definition written in YAML)
 */
class DataHandlerFactory
{
    private const DYNAMIC_ID = 10000;

    /**
     * @var array
     */
    private $settings;

    /**
     * @var EntityConfiguration[]
     */
    private $entityConfigurations = [];

    /**
     * @var array
     */
    private $dataMapPerWorkspace = [];

    /**
     * @var bool[]
     */
    private $suggestedIds = [];

    /**
     * @var int
     */
    private $dynamicIdsPerEntity = [];

    /**
     * @var int[]
     */
    private $staticIdsPerEntity = [];

    /**
     * @param string $yamlFile
     * @return static
     */
    public static function fromYamlFile(string $yamlFile): self
    {
        $yamlContent = file_get_contents($yamlFile);
        $settings = Yaml::parse($yamlContent);
        return new static($settings);
    }

    public function __construct(array $settings)
    {
        $this->settings = $settings;
        $this->buildEntityConfigurations($settings['entitySettings'] ?? []);
        $this->processEntities($this->settings['entities'] ?? []);
    }

    /**
     * @return array
     */
    public function getDataMapPerWorkspace(): array
    {
        return $this->dataMapPerWorkspace;
    }

    /**
     * @return bool[]
     */
    public function getSuggestedIds(): array
    {
        return $this->suggestedIds;
    }

    /**
     * @param array $settings
     * @param string|null $nodeId
     * @param string|null $parentId
     */
    private function processEntities(
        array $settings,
        string $nodeId = null,
        string $parentId = null
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

    /**
     * @param EntityConfiguration $entityConfiguration
     * @param array $itemSettings
     * @param string|null $nodeId
     * @param string|null $parentId
     */
    private function processEntityItem(
        EntityConfiguration $entityConfiguration,
        array $itemSettings,
        string $nodeId = null,
        string $parentId = null
    ): void {
        $values = $this->processEntityValues(
            $entityConfiguration,
            $itemSettings,
            $nodeId,
            $parentId
        );

        $workspaceId = $itemSettings['version']['workspace'] ?? 0;
        $tableName = $entityConfiguration->getTableName();
        $newId = StringUtility::getUniqueId('NEW');
        $this->setInDataMap($tableName, $newId, $values, (int)$workspaceId);

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
                $entityConfiguration->isNode() ? $newId : $nodeId
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

    /**
     * @param EntityConfiguration $entityConfiguration
     * @param array $itemSettings
     * @param array $ancestorIds
     * @param string|null $nodeId
     */
    private function processLanguageVariantItem(
        EntityConfiguration $entityConfiguration,
        array $itemSettings,
        array $ancestorIds,
        string $nodeId = null
    ): void {
        $values = $this->processEntityValues(
            $entityConfiguration,
            $itemSettings,
            $nodeId
        );

        // Language values can be overriden by declared values
        $values = array_merge(
            $entityConfiguration->processLanguageValues($ancestorIds),
            $values
        );

        $tableName = $entityConfiguration->getTableName();
        $newId = StringUtility::getUniqueId('NEW');
        $this->setInDataMap($tableName, $newId, $values, 0);

        foreach ($itemSettings['languageVariants'] ?? [] as $variantItemSettings) {
            $this->processLanguageVariantItem(
                $entityConfiguration,
                $variantItemSettings,
                array_merge($ancestorIds, [$newId]),
                $nodeId
            );
        }
    }

    /**
     * @param EntityConfiguration $entityConfiguration
     * @param array $itemSettings
     * @param string $ancestorId
     */
    private function processVersionVariantItem(
        EntityConfiguration $entityConfiguration,
        array $itemSettings,
        string $ancestorId,
        string $nodeId = null
    ): void {
        $values = $this->processEntityValues(
            $entityConfiguration,
            $itemSettings,
            $nodeId
        );

        $tableName = $entityConfiguration->getTableName();
        $this->setInDataMap($tableName, $ancestorId, $values, (int)$values['workspace']);
    }

    /**
     * @param string string $sourceProperty
     * @param EntityConfiguration $entityConfiguration
     * @param array $itemSettings
     * @param string|null $nodeId
     * @param string|null $parentId
     * @return array
     */
    private function processEntityValues(
        EntityConfiguration $entityConfiguration,
        array $itemSettings,
        string $nodeId = null,
        string $parentId = null
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
        if (isset($itemSettings['version']) &&  empty($itemSettings['version']['workspace'])) {
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

        $suggestedId = $staticId > 0 ? $staticId : $this->incrementDynamicId($entityConfiguration);
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

    /**
     * @param array $settings
     */
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

    /**
     * @param string $entityName
     * @return EntityConfiguration
     */
    private function provideEntityConfiguration(
        string $entityName
    ): EntityConfiguration {
        if (empty($this->entityConfigurations[$entityName])) {
            $this->entityConfigurations[$entityName] = new EntityConfiguration($entityName);
        }
        return $this->entityConfigurations[$entityName];
    }

    /**
     * @param EntityConfiguration $entityConfiguration
     * @param int $suggestedId
     */
    private function addSuggestedId(
        EntityConfiguration $entityConfiguration,
        int $suggestedId
    ): void {
        $identifier = $entityConfiguration->getTableName() . ':' . $suggestedId;
        $this->suggestedIds[$identifier] = true;
    }

    /**
     * @param EntityConfiguration $entityConfiguration
     * @param int $id
     * @return bool
     */
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

    /**
     * @param EntityConfiguration $entityConfiguration
     * @param int $id
     */
    private function addStaticId(
        EntityConfiguration $entityConfiguration,
        int $id
    ): void {
        if (!isset($this->staticIdsPerEntity[$entityConfiguration->getName()])) {
            $this->staticIdsPerEntity[$entityConfiguration->getName()] = [];
        }
        $this->staticIdsPerEntity[$entityConfiguration->getName()][] = $id;
    }

    /**
     * @param EntityConfiguration $entityConfiguration
     * @return int
     */
    private function incrementDynamicId(
        EntityConfiguration $entityConfiguration
    ): int {
        if (!isset($this->dynamicIdsPerEntity[$entityConfiguration->getName()])) {
            $this->dynamicIdsPerEntity[$entityConfiguration->getName()] = static::DYNAMIC_ID;
        }
        return ++$this->dynamicIdsPerEntity[$entityConfiguration->getName()];
    }

    /**
     * Adds values to data map and ensures sorting.
     * Per default DataHandler inserts records to top on according page
     * however, this factory shall insert sequentially one after another.
     *
     * @param string $tableName
     * @param string $identifier
     * @param array $values
     * @param int $workspaceId
     */
    private function setInDataMap(
        string $tableName,
        string $identifier,
        array $values,
        int $workspaceId = 0
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

    /**
     * @param int $workspaceId
     * @param string $tableName
     * @param null|int|string $pageId
     * @return array
     */
    private function filterDataMapByPageId(
        int $workspaceId,
        string $tableName,
        $pageId
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

    /**
     * @param int $workspaceId
     * @param null|int|string $pageId
     * @return null|int|string
     */
    private function resolveDataMapPageId(int $workspaceId, $pageId)
    {
        $normalizePageId = (string)$pageId;
        if ($pageId === null || $normalizePageId{0} !== '-') {
            return $pageId;
        }

        $regularPageId = substr($normalizePageId, 1);
        $resolvedPageId = $this->dataMapPerWorkspace[$workspaceId]['pages'][$regularPageId]['pid'] ?? null;
        return $this->resolveDataMapPageId($workspaceId, $resolvedPageId);
    }
}
