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

/**
 * Model describing a entity configuration used in a data scenario
 */
class EntityConfiguration
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $isNode = false;

    /**
     * @var string|null
     */
    private $tableName;

    /**
     * @var string|null
     */
    private $parentColumnName;

    /**
     * @var string|null
     */
    private $nodeColumnName;

    /**
     * @var array
     */
    private $columnNames = [];

    /**
     * @var array
     */
    private $languageColumnNames = [];

    /**
     * @var array
     */
    private $defaultValues = [];

    /**
     * @var array
     */
    private $valueInstructions = [];

    /**
     * @param string $name
     * @param array $settings
     * @return EntityConfiguration
     */
    public static function fromArray(string $name, array $settings)
    {
        $target = new static($name);

        if (isset($settings['isNode'])) {
            $target->isNode = (bool)$settings['isNode'];
        }

        if (!empty($settings['tableName'])) {
            $target->tableName = $settings['tableName'];
        }

        if (!empty($settings['parentColumnName'])) {
            $target->parentColumnName = $settings['parentColumnName'];
        }

        if (!empty($settings['nodeColumnName'])) {
            $target->nodeColumnName = $settings['nodeColumnName'];
        }

        if (!empty($settings['columnNames'])) {
            $target->columnNames = $settings['columnNames'];
        }

        if (!empty($settings['languageColumnNames'])) {
            $target->languageColumnNames = $settings['languageColumnNames'];
        }

        if (!empty($settings['defaultValues'])) {
            $target->defaultValues = $settings['defaultValues'];
        }

        if (!empty($settings['valueInstructions'])) {
            $target->assertValueInstructions($settings['valueInstructions']);
            $target->valueInstructions = $settings['valueInstructions'];
        }

        return $target;
    }

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isNode(): bool
    {
        return $this->isNode;
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName ?? $this->name;
    }

    /**
     * @return string
     */
    public function getParentColumnName(): ?string
    {
        return $this->parentColumnName;
    }

    /**
     * @return string|null
     */
    public function getNodeColumnName(): ?string
    {
        return $this->nodeColumnName;
    }

    /**
     * @param string $name
     * @return string
     */
    public function resolveColumnName(string $name): string
    {
        return $this->columnNames[$name] ?? $name;
    }

    public function processValues(array $values): array
    {
        $processedValues = $this->defaultValues;

        foreach ($values as $name => $value) {
            $processedValues[$this->resolveColumnName($name)] = $value;
        }
        foreach ($values as $name => $value) {
            $processedValues = $this->assignValueInstructions(
                $processedValues,
                $name,
                $value
            );
        }
        return $processedValues;
    }

    /**
     * @param array $ancestorIds
     * @return array
     */
    public function processLanguageValues(array $ancestorIds): array
    {
        if (empty($ancestorIds)) {
            throw new \RuntimeException(
                'Language ancestor IDs is empty',
                1533744471
            );
        }

        $processedValues = [];

        if (empty($this->languageColumnNames)) {
            return $processedValues;
        }

        $lastAncestorIdsIndex = count($ancestorIds) - 1;
        $lastLanguageColumnNamesIndex = count($this->languageColumnNames) - 1;

        foreach ($this->languageColumnNames as $index => $columnName) {
            if ($index === $lastLanguageColumnNamesIndex || $index > $lastAncestorIdsIndex) {
                $ancestorId = $ancestorIds[$lastAncestorIdsIndex];
            } else {
                $ancestorId = $ancestorIds[$index];
            }
            $processedValues[$columnName] = $ancestorId;
        }

        return $processedValues;
    }

    /**
     * @param array $values
     * @param string $name
     * @param mixed $value
     * @return array
     */
    private function assignValueInstructions(array $values, string $name, $value)
    {
        if (empty($this->valueInstructions[$name][$value])) {
            return $values;
        }
        return array_merge($values, $this->valueInstructions[$name][$value]);
    }

    /**
     * @param array $valueInstructions
     */
    private function assertValueInstructions(array $valueInstructions): void
    {
        foreach ($valueInstructions as $columnName => $valueInstruction) {
            if (empty($valueInstruction) || !is_array($valueInstruction)) {
                throw new \LogicException(
                    sprintf(
                        'Value instruction for column "%s" must be array',
                        $columnName
                    ),
                    1533734368
                );
            }
        }
    }
}
