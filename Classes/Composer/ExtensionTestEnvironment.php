<?php
declare(strict_types=1);
namespace TYPO3\TestingFramework\Composer;

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

use Composer\Script\Event;

/**
 * If a TYPO3 extension should be tested, the extension needs to be embedded in
 * a TYPO3 instance. The composer.json file of the extension then acts as a
 * root composer.json file that creates a TYPO3 project around the extension code
 * in a build folder like "./.Build". The to-test extension then needs to reside
 * in ./.Build/Web/typo3conf/ext. This composer script takes care of this operation
 * and links the current root directory as "./<web-dir>/typo3conf/ext/<extension-key>".
 *
 * This class is added as composer "script" in TYPO3 extensions:
 *
 *   "scripts": {
 *     "post-autoload-dump": [
 *       "@prepare-extension-test-environment"
 *     ],
 *     "prepare-extension-test-structure": [
 *       "TYPO3\TestingFramework\Composer\ExtensionTestEnvironment::prepare"
 *     ]
 *   },
 *
 * It additionally needs the "extension key" (that will become the directory name in
 * typo3conf/ext) and the name of the target directory in the extra section. Example for
 * a extension "my_cool_extension":
 *
 *   "extra": {
 *     "typo3/cms": {
 *       "web-dir": ".Build/Web",
 *       "extension-key": "my_cool_extension"
 *     }
 *   }
 */
final class ExtensionTestEnvironment
{
    /**
     * Link directory that contains the composer.json file as
     * ./<web-dir>/typo3conf/ext/<extension-key>.
     *
     * @param Event $event
     */
    public static function prepare(Event $event): void
    {
        $composerConfigExtraSection = $event->getComposer()->getPackage()->getExtra();
        if (empty($composerConfigExtraSection['typo3/cms']['extension-key'])
            || empty($composerConfigExtraSection['typo3/cms']['web-dir'])
        ) {
            throw new \RuntimeException(
                'This script needs properties in composer.json:'
                    . '"extra" "typo3/cms" "extension-key"'
                    . ' and "extra" "typo3/cms" "web-dir"',
                1540644486
            );
        }
        $extensionKey = $composerConfigExtraSection['typo3/cms']['extension-key'];
        $webDir = $composerConfigExtraSection['typo3/cms']['web-dir'];
        $typo3confExt = __DIR__ . '/../../../../../../' . $webDir . '/typo3conf/ext';
        if (!is_dir($typo3confExt) &&
            !mkdir($typo3confExt, 0775, true) &&
            !is_dir($typo3confExt)
        ) {
            throw new \RuntimeException(
                sprintf('Directory "%s" could not be created', $typo3confExt),
                1540650485
            );
        }
        if (!is_link($typo3confExt . '/' . $extensionKey)) {
            symlink(dirname(__DIR__, 6) . '/', $typo3confExt . '/' . $extensionKey);
        }
    }
}
