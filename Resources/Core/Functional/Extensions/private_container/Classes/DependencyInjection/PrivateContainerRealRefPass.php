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

namespace TYPO3\PrivateContainer\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Unset unused private services. This is used in functional tests to
 * only add private services that are actually used.
 */
class PrivateContainerRealRefPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('typo3.testing-framework.private-container')) {
            return;
        }

        $privateContainer = $container->getDefinition('typo3.testing-framework.private-container');
        $definitions = $container->getDefinitions();
        $privateServices = $privateContainer->getArgument(0);

        foreach ($privateServices as $id => $argument) {
            if (isset($definitions[$target = (string)$argument->getValues()[0]])) {
                $argument->setValues([new Reference($target)]);
            } else {
                unset($privateServices[$id]);
            }
        }

        $privateContainer->replaceArgument(0, $privateServices);
    }
}
