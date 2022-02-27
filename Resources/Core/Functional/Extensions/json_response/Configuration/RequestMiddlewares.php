<?php
/**
 * An array consisting of implementations of middlewares for a middleware stack to be registered
 *
 *  'stackname' => [
 *      'middleware-identifier' => [
 *         'target' => classname or callable
 *         'before/after' => array of dependencies
 *      ]
 *   ]
 */
return [
    'frontend' => [
        'typo3/json-response/encoder' => [
            'target' => \TYPO3\JsonResponse\Encoder::class,
            'before' => [
                'typo3/cms-frontend/timetracker',
            ],
        ],
        'typo3/json-response/frontend-user-authentication' => [
            'target' => \TYPO3\JsonResponse\Middleware\FrontendUserHandler::class,
            'after' => [
                'typo3/cms-frontend/backend-user-authentication',
            ],
            'before' => [
                'typo3/cms-frontend/base-redirect-resolver',
            ],
        ],
        'typo3/json-response/backend-user-authentication' => [
            'target' => \TYPO3\JsonResponse\Middleware\BackendUserHandler::class,
            'after' => [
                'typo3/cms-frontend/backend-user-authentication',
            ],
            'before' => [
                'typo3/cms-frontend/base-redirect-resolver',
            ],
        ],
    ],
];
