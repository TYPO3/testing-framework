<?php
declare(strict_types = 1);
namespace TYPO3\TestingFramework\Core\Tests\Unit\Functional\Framework\DataHandler;

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

use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\DataSet;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;


/**
 * Test Case
 */
class DataSetTest extends UnitTestCase
{

    /**
     * @test
     */
    public function handlesUtf8WithoutBom(): void
    {
        $csvFile = __DIR__ . '/../../../Fixtures/BOM/WithoutBom.csv';
        $dataSet = DataSet::read($csvFile);
        $tableName = $dataSet->getTableNames()[0];
        $this->assertEquals(strlen('pages'), strlen($tableName));
    }

    /**
     * @test
     */
    public function handlesUtf8WithBom(): void
    {
        $csvFile = __DIR__ . '/../../../Fixtures/BOM/WithBom.csv';
        $dataSet = DataSet::read($csvFile);
        $tableName = $dataSet->getTableNames()[0];
        $this->assertEquals(strlen('pages'), strlen($tableName));
    }

}
