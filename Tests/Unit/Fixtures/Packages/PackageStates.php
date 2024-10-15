<?php

// This is a fixture file and is intended to be not in sorted state to verify that sorting works correctly.
return [
    'packages' => [
        'package2' => [
            'packagePath' => 'Tests/Unit/Fixtures/Packages/package2/',
        ],
        'extbase' => [
            'packagePath' => '.Build/vendor/typo3/cms-extbase/',
        ],
        'extension_with_extemconf' => [
            'packagePath' => 'Tests/Unit/Fixtures/Packages/package-with-extemconf/',
        ],
        'package1' => [
            'packagePath' => 'Tests/Unit/Fixtures/Packages/package1/',
        ],
        'fluid' => [
            'packagePath' => '.Build/vendor/typo3/cms-fluid/',
        ],
        'package0' => [
            'packagePath' => 'Tests/Unit/Fixtures/Packages/package0/',
        ],
        'backend' => [
            'packagePath' => '.Build/vendor/typo3/cms-backend/',
        ],
        'frontend' => [
            'packagePath' => '.Build/vendor/typo3/cms-frontend/',
        ],
        'core' => [
            'packagePath' => '.Build/vendor/typo3/cms-core/',
        ],
    ],
    'version' => 5,
];
