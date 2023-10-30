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

namespace TYPO3\TestingFramework\Core\Acceptance\Helper;

/**
 * This is an ugly hack exclusively for testing-framework v7 to allow
 * both codeception 4 and 5 to be used for core v11 and v12 at the same time.
 *
 * Problem is the codeception API is hard breaking between codeception 4 and 5,
 * especially due to new type hints on properties that we have to use.
 */
if (method_exists(\Codeception\Suite::class, 'backupGlobals')) {
    class_alias(LoginCodeceptionFive::class, 'TYPO3\\TestingFramework\\Core\\Acceptance\\Helper\\LoginConditionalParent');
} else {
    class_alias(LoginCodeceptionFour::class, 'TYPO3\\TestingFramework\\Core\\Acceptance\\Helper\\LoginConditionalParent');
}

class Login extends LoginConditionalParent {}
