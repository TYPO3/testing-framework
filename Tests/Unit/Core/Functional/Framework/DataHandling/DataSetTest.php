<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Tests\Unit\Core\Functional\Framework\DataHandling;

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
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\DataSet;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class DataSetTest extends UnitTestCase
{
    #[Test]
    public function handlesUtf8WithoutBom(): void
    {
        $csvFile = __DIR__ . '/../../../Fixtures/BOM/WithoutBom.csv';
        $dataSet = DataSet::read($csvFile);
        $tableName = $dataSet->getTableNames()[0];
        self::assertEquals(strlen('pages'), strlen($tableName));
    }

    #[Test]
    public function handlesUtf8WithBom(): void
    {
        $csvFile = __DIR__ . '/../../../Fixtures/BOM/WithBom.csv';
        $dataSet = DataSet::read($csvFile);
        $tableName = $dataSet->getTableNames()[0];
        self::assertEquals(strlen('pages'), strlen($tableName));
    }

    #[Test]
    public function notNullJsonFieldDataWithDoubleQuotationCanBeDecoded(): void
    {
        $csvFile = __DIR__ . '/../../../Fixtures/Json/WithJsonValueQuotedWithDoubleQuotes.csv';
        $dataSet = DataSet::read($csvFile);
        $tableName = $dataSet->getTableNames()[0];
        self::assertSame('be_users', $tableName);
        $jsonValue = $dataSet->getElements($tableName)[1]['mfa'];
        $decoded = json_decode($jsonValue, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['name' => 'value', 'name2' => ['name3' => 'subvalue', 'empty-array' => []]], $decoded);
    }
}
