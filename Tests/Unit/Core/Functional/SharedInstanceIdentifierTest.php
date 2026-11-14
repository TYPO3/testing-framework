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

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3\TestingFramework\Tests\Unit\Core\Functional\Fixtures\NotSharedInstance;
use TYPO3\TestingFramework\Tests\Unit\Core\Functional\Fixtures\SharedInstanceFirst;
use TYPO3\TestingFramework\Tests\Unit\Core\Functional\Fixtures\SharedInstanceOtherExtensions;
use TYPO3\TestingFramework\Tests\Unit\Core\Functional\Fixtures\SharedInstanceOwnDatabaseReset;
use TYPO3\TestingFramework\Tests\Unit\Core\Functional\Fixtures\SharedInstanceSecond;

final class SharedInstanceIdentifierTest extends UnitTestCase
{
    #[Test]
    public function testCaseClassesNotSharingTheirInstanceAreIdentifiedByClassName(): void
    {
        self::assertSame(substr(sha1(NotSharedInstance::class), 0, 7), NotSharedInstance::instanceIdentifier());
    }

    #[Test]
    public function testCaseClassesSharingTheirInstanceWithTheSameConfigurationGetTheSameIdentifier(): void
    {
        self::assertSame(SharedInstanceFirst::instanceIdentifier(), SharedInstanceSecond::instanceIdentifier());
    }

    #[Test]
    public function testCaseClassesSharingTheirInstanceWithDifferentExtensionsGetDifferentIdentifiers(): void
    {
        self::assertNotSame(SharedInstanceFirst::instanceIdentifier(), SharedInstanceOtherExtensions::instanceIdentifier());
    }

    #[Test]
    public function testCaseClassesSharingTheirInstanceWithOwnDatabaseResetGetDifferentIdentifiers(): void
    {
        self::assertNotSame(SharedInstanceFirst::instanceIdentifier(), SharedInstanceOwnDatabaseReset::instanceIdentifier());
    }
}
