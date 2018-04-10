<?php
namespace TueFind\Module\Config;

$config = [
    'controllers' => [
        'factories' => [
            'pdaproxy' => 'TueFind\Controller\Factory::getPDAProxyController',
            'proxy' => 'TueFind\Controller\Factory::getProxyController',
            'StaticPage' => 'TueFind\Controller\Factory::getStaticPageController',
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
                    ],
                ],
            ],
            'pdaproxy-load' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/PDAProxy/Load',
                    'defaults' => [
                        'controller' => 'PDAProxy',
                        'action'     => 'Load',
                    ],
                ],
            ],
        ],
    ],
    'vufind' => [
        'plugin_managers' => [
            'recorddriver' => [
                'factories' => [
                    'solrdefault' => 'TueFind\RecordDriver\Factory::getSolrDefault',
                    'solrmarc' => 'TueFind\RecordDriver\Factory::getSolrMarc',
                ],
            ],
            'search_results' => [
                'factories' => [
                    'solr' => 'TueFind\Search\Results\Factory::getSolr',
                ],
            ],
        ],
    ],
];

return $config;
