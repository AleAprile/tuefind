<?php
namespace TuFind\Module\Config;

$config = [
    'controllers' => [
        'factories' => [
            'pdaproxy' => 'TuFind\Controller\Factory::getPDAProxyController',
            'proxy' => 'TuFind\Controller\Factory::getProxyController',
        ],
    ],
    'router' => [
        'routes' => [
            'proxy-load' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/Proxy/Load',
                    'defaults' => [
                        'controller' => 'Proxy',
                        'action'     => 'Load',
                    ]
                ]
            ],
            'pdaproxy-load' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/PDAProxy/Load',
                    'defaults' => [
                        'controller' => 'PDAProxy',
                        'action'     => 'Load',
                    ]
                ]
            ]
        ]
    ]
];

return $config;
