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

namespace TYPO3\TestingFramework\Core\Functional\Framework\DataHandling;

use Doctrine\DBAL\Types\JsonType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Testbase;

/**
 * Internal worker class managing .csv fixture file contents.
 *
 * This is used in functional and acceptance tests to load .csv fixture files
 * into database and to assert current database state with .csv fixture files.
 *
 * A .csv file can include table rows from multiple tables.
 * When importing .csv files to database, "default" values from TCA for this table are applied.
 *
 * A typical example .csv file looks like this, this specifies two rows of
 * table "pages" and two rows to table "tt_content". The fields have to
 * exist in the table:
 *
 * "pages"
 * ,"uid","pid","sorting","deleted","t3_origuid","t3ver_wsid","t3ver_state","t3ver_stage","t3ver_oid","title"
 * ,1,0,256,0,0,0,0,0,0,"Root Page"
 * ,2,0,256,0,0,0,0,0,0,"Another Page"
 * "tt_content"
 * ,"uid","pid","sorting","deleted","sys_language_uid","l18n_parent","t3_origuid","t3ver_wsid","t3ver_state","t3ver_stage","t3ver_oid","header"
 * ,1,1,256,0,0,0,0,0,0,0,0,"Regular Element #1"
 * ,1,1,512,0,0,0,0,0,0,0,0,"Regular Element #2"
 *
 * @internal Directly using this class is discouraged, it may change any time.
 *           Use API methods like importCSVDataSet() (in functional & acceptance tests)
 *           and assertCSVDataSet() (in functional tests) instead.
 */
final readonly class DataSet
{
    /**
     * Private constructor: An instance of this class is returned using DataSet::read()
     */
    private function __construct(
        private array $data
    ) {}

    /**
     * Read a file and import it.
     *
     * @param string $path Absolute path to the CSV file containing the data set to load
     * @internal API is exposed using importCSVDataSet() in FunctionalTestCase and BackendEnvironment acceptance test base class.
     */
    public static function import(string $path): void
    {
        $dataSet = self::read($path, true, true);
        foreach ($dataSet->getTableNames() as $tableName) {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);
            $platform = $connection->getDatabasePlatform();
            // @todo Check if we can use the cached schema information here instead.
            $tableDetails = $connection->createSchemaManager()->introspectTable($tableName);
            foreach ($dataSet->getElements($tableName) as $element) {
                // Some DBMS like postgresql are picky about inserting blob types with correct cast, setting
                // types correctly (like Connection::PARAM_LOB) allows doctrine to create valid SQL
                $types = [];
                foreach ($element as $columnName => $columnValue) {
                    $types[$columnName] = $columnType = $tableDetails->getColumn($columnName)->getType();
                    // JSON-Field data is converted (json-encode'd) within $connection->insert(), and since json field
                    // data can only be provided json encoded in the csv dataset files, we need to decode them here.
                    if ($columnValue !== null && $columnType instanceof JsonType) {
                        $element[$columnName] = $columnType->convertToPHPValue($columnValue, $platform);
                    }
                }
                // Insert the row
                $connection->insert($tableName, $element, $types);
            }
            Testbase::resetTableSequences($connection, $tableName);
        }
    }

    /**
     * Main entry method: Get at absosulete (!) path to a .csv file, read it and return an instance of self
     */
    public static function read(string $fileName, bool $applyDefaultValues = false, bool $checkForDuplicates = false): self
    {
        $data = self::parseData(self::readData($fileName), $fileName, $checkForDuplicates);
        if ($applyDefaultValues) {
            $data = self::applyDefaultValues($data);
        }
        return new DataSet($data);
    }

    /**
     * Get the list of table names included in a loaded .csv file
     */
    public function getTableNames(): array
    {
        return array_keys($this->data);
    }

    /**
     * Get the list of fields of a single table included in a loaded .csv file
     */
    public function getFields(string $tableName): ?array
    {
        $fields = null;
        if (isset($this->data[$tableName]['fields'])) {
            $fields = $this->data[$tableName]['fields'];
        }
        return $fields;
    }

    /**
     * When a table has a "uid" field as primary key, this method
     * return this field array key from getFields(). This is set when a
     * table has a column named "uid".
     */
    public function getIdIndex(string $tableName): ?int
    {
        $idIndex = null;
        if (isset($this->data[$tableName]['idIndex'])) {
            $idIndex = $this->data[$tableName]['idIndex'];
        }
        return $idIndex;
    }

    /**
     * When a table has a "hash" field as primary key, this method
     * return this field array key from getFields(). This is set when a
     * table has a column named "hash".
     */
    public function getHashIndex(string $tableName): ?int
    {
        $hashIndex = null;
        if (isset($this->data[$tableName]['hashIndex'])) {
            $hashIndex = $this->data[$tableName]['hashIndex'];
        }
        return $hashIndex;
    }

    /**
     * Return a list of rows of given table. Keys are the uid or hash
     * index fields of that table.
     */
    public function getElements(string $tableName): array
    {
        $elements = [];
        if (isset($this->data[$tableName]['elements'])) {
            $elements = $this->data[$tableName]['elements'];
        }
        return $elements;
    }

    /**
     * Read a file and return an array of all lines as csv parsed array
     */
    private static function readData(string $fileName): array
    {
        if (!file_exists($fileName)) {
            throw new \RuntimeException('File "' . $fileName . '" does not exist', 1476049619);
        }
        $rawData = [];
        $fileHandle = fopen($fileName, 'r');
        // UTF-8 Files starting with BOM will break the first field in the first line
        // which is usually the first table name. That‘s why we omit a BOM at the beginning.
        $bom = "\xef\xbb\xbf";
        if (fgets($fileHandle, 4) !== $bom) {
            // BOM not found - rewind pointer to start of file.
            rewind($fileHandle);
        }
        while (!feof($fileHandle) && ($values = fgetcsv($fileHandle, 0, ',', '"', '\\')) !== false) {
            $rawData[] = $values;
        }
        fclose($fileHandle);
        return $rawData;
    }

    /**
     * Parse lines of the CSV array to a 'table' and 'table-row' structure.
     *
     * Special value treatment:
     * + "\NULL" to treat as NULL value
     */
    private static function parseData(array $rawData, string $fileName, bool $checkForDuplicates): array
    {
        $data = [];
        $tableName = null;
        $fieldCount = null;
        $idIndex = null;
        // Table sys_refindex has no uid but a hash field as primary key
        $hashIndex = null;
        foreach ($rawData as $values) {
            if (!empty($values[0])) {
                // Skip comment lines, starting with "#"
                if ($values[0][0] === '#') {
                    continue;
                }
                $tableName = $values[0];
                $fieldCount = null;
                $idIndex = null;
                $hashIndex = null;
                if (!isset($data[$tableName])) {
                    $data[$tableName] = [];
                }
            } elseif (implode('', $values) === '') {
                $tableName = null;
                $fieldCount = null;
                $idIndex = null;
                $hashIndex = null;
            } elseif ($tableName !== null && (string)$values[1] !== '') {
                array_shift($values);
                if (!isset($data[$tableName]['fields'])) {
                    $data[$tableName]['fields'] = [];
                    foreach ($values as $value) {
                        if ((string)$value === '') {
                            continue;
                        }
                        $data[$tableName]['fields'][] = $value;
                        $fieldCount = count($data[$tableName]['fields']);
                    }
                    if (in_array('uid', $values, true)) {
                        $idIndex = array_search('uid', $values);
                        $data[$tableName]['idIndex'] = $idIndex;
                    }
                    if (in_array('hash', $values, true)) {
                        $hashIndex = array_search('hash', $values);
                        $data[$tableName]['hashIndex'] = $hashIndex;
                    }
                } else {
                    if (!isset($data[$tableName]['elements'])) {
                        $data[$tableName]['elements'] = [];
                    }
                    $values = array_slice($values, 0, $fieldCount);
                    foreach ($values as &$value) {
                        if ($value === '\\NULL') {
                            $value = null;
                        }
                    }
                    unset($value);
                    $element = array_combine($data[$tableName]['fields'], $values);
                    if ($idIndex !== null) {
                        if ($checkForDuplicates && is_array($data[$tableName]['elements'][$values[$idIndex]] ?? false)) {
                            throw new \RuntimeException(
                                sprintf(
                                    'DataSet "%s" containes a duplicate record for idField "%s.uid" => %s',
                                    $fileName,
                                    $tableName,
                                    $values[$idIndex]
                                ),
                                1690538506
                            );
                        }
                        $data[$tableName]['elements'][$values[$idIndex]] = $element;
                    } elseif ($hashIndex !== null) {
                        if ($checkForDuplicates && is_array($data[$tableName]['elements'][$values[$hashIndex]] ?? false)) {
                            throw new \RuntimeException(
                                sprintf(
                                    'DataSet "%s" containes a duplicate record for idHash "%s.hash" => %s',
                                    $fileName,
                                    $tableName,
                                    $values[$hashIndex]
                                ),
                                1690541069
                            );
                        }
                        $data[$tableName]['elements'][$values[$hashIndex]] = $element;
                    } else {
                        $data[$tableName]['elements'][] = $element;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Apply TCA default values to missing fields on the imported scenario data-set.
     * This is basically required for running the functional tests in a SQL strict mode environment.
     */
    private static function applyDefaultValues(array $data): array
    {
        foreach ($data as $tableName => $sections) {
            if (empty($GLOBALS['TCA'][$tableName]['columns'])) {
                continue;
            }
            $fields = $sections['fields'];
            foreach ($GLOBALS['TCA'][$tableName]['columns'] as $tcaFieldName => $tcaFieldConfiguration) {
                // Skip if field was already imported
                if (in_array($tcaFieldName, $fields, true)) {
                    continue;
                }
                // Skip if field is an enable-column (it's expected that those fields have proper DBMS defaults)
                if (!empty($GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns']) && in_array($tcaFieldName, $GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns'], true)) {
                    continue;
                }
                // Skip if no default value is defined in the accordant TCA definition (NULL values might occur as well)
                if (empty($tcaFieldConfiguration['config']) || !array_key_exists('default', $tcaFieldConfiguration['config'])) {
                    continue;
                }
                $data[$tableName]['fields'][] = $tcaFieldName;
                foreach ($data[$tableName]['elements'] as &$element) {
                    $element[$tcaFieldName] = $tcaFieldConfiguration['config']['default'];
                }
            }
        }
        return $data;
    }
}
