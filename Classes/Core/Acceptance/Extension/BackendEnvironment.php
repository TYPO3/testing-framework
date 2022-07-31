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

namespace TYPO3\TestingFramework\Core\Acceptance\Extension;

use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * This is an ugly hack exclusively for testing-framework v7 to allow
 * both codeception 4 (core v11) and 5 (core v12) at the same time.
 *
 * Problem is the codeception API is hard breaking between codeception 4 and 5,
 * especially due to new type hints on properties that we have to use.
 */
if (((new Typo3Version())->getMajorVersion() >= 12)) {
    class_alias(BackendEnvironmentCoreTwelve::class, 'TYPO3\\TestingFramework\\Core\\Acceptance\\Extension\\BackendEnvironmentCoreConditionalParent');
} else {
    class_alias(BackendEnvironmentCoreEleven::class, 'TYPO3\\TestingFramework\\Core\\Acceptance\\Extension\\BackendEnvironmentCoreConditionalParent');
}

abstract class BackendEnvironment extends BackendEnvironmentCoreConditionalParent
{
}
