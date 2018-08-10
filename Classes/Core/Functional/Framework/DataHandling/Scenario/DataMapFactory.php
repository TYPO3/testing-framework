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
 * Factory for DataHandler data map information parsed from a structured array
 * (or more specifically a scenario definition written in YAML)
 */
class DataMapFactory
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
    private $dataMap = [];

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
     * @return DataMapFactory
     */
    public static function fromYamlFile(string $yamlFile): DataMapFactory
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
    public function getDataMap(): array
    {
        return $this->dataMap;
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

        $tableName = $entityConfiguration->getTableName();
        $newId = StringUtility::getUniqueId('NEW');
        // Placeholder to preserve creation order
        $this->setInDataMap($tableName, $newId);

        foreach ($itemSettings['languageVariants'] as $variantItemSettings) {
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

        // Finally assign values
        $this->setInDataMap($tableName, $newId, $values);
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
        // Placeholder to preserve creation order
        $this->setInDataMap($tableName, $newId);

        foreach ($itemSettings['languageVariants'] as $variantItemSettings) {
            $this->processLanguageVariantItem(
                $entityConfiguration,
                $variantItemSettings,
                array_merge($ancestorIds, [$newId]),
                $nodeId
            );
        }

        // Finally assign values
        $this->setInDataMap($tableName, $newId, $values);
    }

    /**
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
        if (empty($itemSettings['self']) || !is_array($itemSettings['self'])) {
            throw new \LogicException(
                sprintf(
                    'Missing "self" declaration for entity "%s"',
                    $entityConfiguration->getName()
                ),
                1533734369
            );
        }

        $staticId = (int)($itemSettings['self']['id'] ?? 0);
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
        $values = $entityConfiguration->processValues($itemSettings['self']);
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
     */
    private function setInDataMap(
        string $tableName,
        string $identifier,
        array $values = []
    ): void {
        if (empty($values)) {
            $this->dataMap[$tableName][$identifier] = $values;
            return;
        }

        $tableDataMap = $this->filterDataMapByPageId($tableName, $values['pid'] ?? null);
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

        $this->dataMap[$tableName][$identifier] = $values;
    }

    /**
     * @param string $tableName
     * @param null|int|string $pageId
     * @return array
     */
    private function filterDataMapByPageId(string $tableName, $pageId): array
    {
        if ($pageId === null) {
            return [];
        }

        return array_filter(
            $this->dataMap[$tableName] ?? [],
            function (array $item) use ($pageId) {
                return ($item['pid'] ?? null) === $pageId;
            }
        );
    }
}
