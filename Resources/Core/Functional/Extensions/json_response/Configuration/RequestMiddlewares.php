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
                'typo3/cms-frontend/timetracker'
            ]
        ],
    ]
];
