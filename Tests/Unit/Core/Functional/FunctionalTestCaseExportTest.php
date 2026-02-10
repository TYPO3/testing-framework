<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Tests\Unit\Core\Functional;

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

use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\DataSet;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FunctionalTestCaseExportTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('formatCsvValueDataProvider')]
    public function formatCsvValueFormatsCorrectly(mixed $input, ?Type $columnType, string $expected): void
    {
        $subject = $this->getAccessibleMock(
            FunctionalTestCase::class,
            null,
            [],
            '',
            false
        );
        $result = $subject->_call('formatCsvValue', $input, $columnType);
        self::assertSame($expected, $result);
    }

    public static function formatCsvValueDataProvider(): array
    {
        return [
            'null value becomes \\NULL' => [
                'input' => null,
                'columnType' => new StringType(),
                'expected' => '\\NULL',
            ],
            'empty string becomes quoted empty' => [
                'input' => '',
                'columnType' => new StringType(),
                'expected' => '""',
            ],
            'plain integer' => [
                'input' => 42,
                'columnType' => new IntegerType(),
                'expected' => '42',
            ],
            'plain string without special chars' => [
                'input' => 'hello',
                'columnType' => new StringType(),
                'expected' => '"hello"',
            ],
            'string with comma is quoted' => [
                'input' => 'hello,world',
                'columnType' => new StringType(),
                'expected' => '"hello,world"',
            ],
            'string with double quote is quoted and escaped' => [
                'input' => 'say "hello"',
                'columnType' => new StringType(),
                'expected' => '"say ""hello"""',
            ],
            'string with newline is quoted' => [
                'input' => "line1\nline2",
                'columnType' => new StringType(),
                'expected' => "\"line1\nline2\"",
            ],
            'string with carriage return is quoted' => [
                'input' => "line1\rline2",
                'columnType' => new StringType(),
                'expected' => "\"line1\rline2\"",
            ],
            'string with backslash is quoted' => [
                'input' => 'path\\to\\file',
                'columnType' => new StringType(),
                'expected' => '"path\\to\\file"',
            ],
            'zero as integer' => [
                'input' => 0,
                'columnType' => new SmallIntType(),
                'expected' => '0',
            ],
            'zero as string' => [
                'input' => '0',
                'columnType' => new IntegerType(),
                'expected' => '0',
            ],
            'plain numeric string' => [
                'input' => '256',
                'columnType' => new IntegerType(),
                'expected' => '256',
            ],
        ];
    }

    #[Test]
    public function exportedCsvCanBeReadBackByDataSetRead(): void
    {
        $subject = $this->getAccessibleMock(
            FunctionalTestCase::class,
            null,
            [],
            '',
            false
        );

        // Simulate CSV output generation using the private formatCsvValue method
        $tables = [
            'pages' => [
                'tableColumnTypes' => [
                    'uid' => new IntegerType(),
                    'pid' => new IntegerType(),
                    'title' => new StringType(),
                    'deleted' => new BooleanType(),
                ],
                'records' => [
                    ['uid' => 1, 'pid' => 0, 'title' => 'Root Page', 'deleted' => 0],
                    ['uid' => 2, 'pid' => 1, 'title' => 'Sub Page', 'deleted' => 0],
                ],
            ],
            'tt_content' => [
                'tableColumnTypes' => [
                    'uid' => new IntegerType(),
                    'pid' => new IntegerType(),
                    'header' => new StringType(),
                    'bodytext' => new TextType(),
                ],
                'records' => [
                    ['uid' => 1, 'pid' => 1, 'header' => 'Element #1', 'bodytext' => null],
                    ['uid' => 2, 'pid' => 1, 'header' => 'With "quotes"', 'bodytext' => 'Some,text'],
                ],
            ],
        ];

        // Build CSV output the same way exportCSVDataSet does
        $output = '';
        $firstTable = true;
        foreach ($tables as $tableName => $tableData) {
            $tableColumnTypes = $tableData['tableColumnTypes'];
            $fields = array_keys($tableColumnTypes);
            $records = $tableData['records'];

            if (!$firstTable) {
                $output .= "\n";
            }
            $firstTable = false;

            $output .= '"' . $tableName . '"' . str_repeat(',', count($fields)) . "\n";
            $output .= ',' . implode(',', $fields) . "\n";
            foreach ($records as $record) {
                $values = [];
                foreach ($fields as $field) {
                    $values[] = $subject->_call('formatCsvValue', $record[$field] ?? null, $tableColumnTypes[$field] ?? null);
                }
                $output .= ',' . implode(',', $values) . "\n";
            }
        }

        // Write to temp file
        $tempFile = sys_get_temp_dir() . '/typo3_testing_export_test_' . uniqid() . '.csv';
        file_put_contents($tempFile, $output);

        try {
            // Read back with DataSet::read()
            $dataSet = DataSet::read($tempFile);

            // Verify table names
            self::assertSame(['pages', 'tt_content'], $dataSet->getTableNames());

            // Verify pages fields
            self::assertSame(['uid', 'pid', 'title', 'deleted'], $dataSet->getFields('pages'));

            // Verify pages elements
            $pagesElements = $dataSet->getElements('pages');
            self::assertCount(2, $pagesElements);
            self::assertSame('1', $pagesElements[1]['uid']);
            self::assertSame('0', $pagesElements[1]['pid']);
            self::assertSame('Root Page', $pagesElements[1]['title']);
            self::assertSame('0', $pagesElements[1]['deleted']);
            self::assertSame('2', $pagesElements[2]['uid']);
            self::assertSame('Sub Page', $pagesElements[2]['title']);

            // Verify tt_content fields
            self::assertSame(['uid', 'pid', 'header', 'bodytext'], $dataSet->getFields('tt_content'));

            // Verify tt_content elements including NULL and special chars
            $ttContentElements = $dataSet->getElements('tt_content');
            self::assertCount(2, $ttContentElements);
            self::assertNull($ttContentElements[1]['bodytext']);
            self::assertSame('With "quotes"', $ttContentElements[2]['header']);
            self::assertSame('Some,text', $ttContentElements[2]['bodytext']);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function exportCsvDataSetThrowsExceptionForNonExistentDirectory(): void
    {
        $subject = $this->getAccessibleMock(
            FunctionalTestCase::class,
            null,
            [],
            '',
            false
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1732006381);
        $subject->_call('exportCSVDataSet', '/non/existent/path/export.csv', ['pages']);
    }
}
