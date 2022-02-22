<?php

declare(strict_types=1);

namespace TYPO3\PrivateContainer;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container, ContainerBuilder $containerBuilder) {
    // Register services similar to symfony testing: Allow private services if they are used
    // by other services. This is a special testing-framework quirk to allow get'ting private
    // (as-in public:false) services.
    $containerBuilder->addCompilerPass(new DependencyInjection\PrivateContainerWeakRefPass(), PassConfig::TYPE_BEFORE_REMOVING, -32);
    $containerBuilder->addCompilerPass(new DependencyInjection\PrivateContainerRealRefPass(), PassConfig::TYPE_AFTER_REMOVING);
};
