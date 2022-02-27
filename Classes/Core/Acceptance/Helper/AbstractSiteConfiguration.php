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

use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Tests\Functional\SiteHandling\SiteBasedTestTrait;

/**
 * @deprecated Unused. Will be dropped with 7.x major version.
 */
abstract class AbstractSiteConfiguration
{
    use SiteBasedTestTrait;

    /**
     * @var array
     */
    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
        'DK' => ['id' => 1, 'title' => 'Dansk', 'locale' => 'da_DK.UTF8'],
        'DE' => ['id' => 2, 'title' => 'German', 'locale' => 'de_DE.UTF8'],
    ];

    /**
     * @var AcceptanceTester
     *
     * currently unused, but let's keep it for the time being. It will come in handy.
     */
    protected $tester;

    public function adjustSiteConfiguration(): void
    {
        $sitesDir = ORIGINAL_ROOT . 'typo3temp/var/tests/acceptance/typo3conf/sites';
        $scandir = scandir($sitesDir);
        if (!empty($scandir)) {
            $identifer = end(array_diff($scandir, ['.', '..']));
        } else {
            $identifer = 'local-testing';
        }

        $configuration = $this->buildSiteConfiguration(1, '/');
        $configuration += [
            'langugages' => [
                $this->buildDefaultLanguageConfiguration('EN', '/en/'),
                $this->buildLanguageConfiguration('DK', '/dk/'),
                $this->buildLanguageConfiguration('DE', '/de/'),
            ],
        ];

        $siteConfiguration = new SiteConfiguration($sitesDir);
        $siteConfiguration->write($identifer, $configuration);
    }
}
