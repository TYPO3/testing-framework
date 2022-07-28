<?php

declare(strict_types=1);
namespace TYPO3\TestingFramework\Core\Acceptance\Helper;

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

use TYPO3\CMS\Core\Information\Typo3Version;

if (((new Typo3Version())->getMajorVersion() >= 12)) {
    class_alias(LoginTwelve::class, 'TYPO3\\TestingFramework\\Core\\Acceptance\\Helper\\LoginConditionalParent');
} else {
    class_alias(LoginEleven::class, 'TYPO3\\TestingFramework\\Core\\Acceptance\\Helper\\LoginConditionalParent');
}

class Login extends LoginConditionalParent
{
}
