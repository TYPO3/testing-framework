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
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Register used private services. This is a special testing-framework
 * functional service pass to allow $this->get() of private services in
 * functional tests.
 */
class PrivateContainerWeakRefPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $privateServices = [];
        $definitions = $container->getDefinitions();

        foreach ($definitions as $id => $definition) {
            if ($id &&
                $id[0] !== '.' &&
                (!$definition->isPublic() || $definition->isPrivate() || $definition->hasTag('container.private')) &&
                !$definition->hasErrors() &&
                !$definition->isAbstract()
            ) {
                $privateServices[$id] = new Reference($id, ContainerBuilder::IGNORE_ON_UNINITIALIZED_REFERENCE);
            }
        }

        $aliases = $container->getAliases();

        foreach ($aliases as $id => $alias) {
            if ($id && $id[0] !== '.' && (!$alias->isPublic() || $alias->isPrivate())) {
                while (isset($aliases[$target = (string)$alias])) {
                    $alias = $aliases[$target];
                }
                if (isset($definitions[$target]) && !$definitions[$target]->hasErrors() && !$definitions[$target]->isAbstract()) {
                    $privateServices[$id] = new Reference($target, ContainerBuilder::IGNORE_ON_UNINITIALIZED_REFERENCE);
                }
            }
        }

        if ($privateServices) {
            $id = (string)ServiceLocatorTagPass::register($container, $privateServices);
            $container->setDefinition('typo3.testing-framework.private-container', $container->getDefinition($id))->setPublic(true);
            $container->removeDefinition($id);
        }
    }
}
