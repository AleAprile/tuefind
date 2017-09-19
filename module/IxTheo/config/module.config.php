<?php
namespace Ixtheo\Module\Config;

$config = [
    'vufind' => [
        'plugin_managers' => [
            'auth' => [
                'invokables' => [
                    'database' => 'IxTheo\Auth\Database',
                ],
            ],
            'autocomplete' => [
                'factories' => [
                    'solr' => 'IxTheo\Autocomplete\Factory::getSolr',
                ],
            ],
            'db_table' => [
                'invokables' => [
                    'IxTheoUser' => 'IxTheo\Db\Table\IxTheoUser',
                    'pdasubscription' => 'IxTheo\Db\Table\PDASubscription',
                    'subscription' => 'IxTheo\Db\Table\Subscription',

                ],
            ],
            'recorddriver' => [
                'factories' => [
                    'solrdefault' => 'IxTheo\RecordDriver\Factory::getSolrDefault',
                    'solrmarc' => 'IxTheo\RecordDriver\Factory::getSolrMarc',
                ],
            ],
            'search_backend' => [
            	'factories' => [
                    'Solr' => 'IxTheo\Search\Factory\SolrDefaultBackendFactory',
                ],
            ],
            'search_options' => [
                'factories' => [
                    'KeywordChainSearch' => 'IxTheo\Search\Options\Factory::getKeywordChainSearch',
                    'PDASubscriptions' => 'IxTheo\Search\Options\Factory::getPDASubscriptions',
                    'Subscriptions' => 'IxTheo\Search\Options\Factory::getSubscriptions',
                ],
            ],
            'search_params' => [
                'abstract_factories' => ['IxTheo\Search\Params\PluginFactory'],
            ],
            'search_results' => [
                'factories' => [
                    'KeywordChainSearch' => 'IxTheo\Search\Results\Factory::getKeywordChainSearch',
                    'pdasubscriptions' => 'IxTheo\Search\Results\Factory::getPDASubscriptions',
                    'Subscriptions' => 'IxTheo\Search\Results\Factory::getSubscriptions',
                ],
            ],
        ],
        'recorddriver_tabs' => [
            'VuFind\RecordDriver\SolrMarc' => [
                'tabs' => [
                    // Disable certain tabs (overwrite value with null)
                    'Excerpt' => null,
                    'HierarchyTree' => null,
                    'Holdings' => null,
                    'Map' => null,
                    'Preview' => null,
                    'Reviews' => null,
                    'Similar' => null,
                    'TOC' => null,
                    'UserComments' => null,
                ],
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            'browse' => 'IxTheo\Controller\Factory::getBrowseController',
            'record' => 'IxTheo\Controller\Factory::getRecordController',
        ],
        'invokables' => [
            'alphabrowse' => 'IxTheo\Controller\AlphabrowseController',
            'BibleRangeSearch' => 'IxTheo\Controller\Search\BibleRangeSearchController',
            'feedback' => 'IxTheo\Controller\FeedbackController',
            'KeywordChainSearch' => 'IxTheo\Controller\Search\KeywordChainSearchController',
            'MyResearch' => 'IxTheo\Controller\MyResearchController',
            'Pipeline' => 'IxTheo\Controller\Pipeline',
            'search' => 'IxTheo\Controller\SearchController',
            'StaticPage' => 'IxTheo\Controller\StaticPageController',
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'subscriptions' => 'IxTheo\Controller\Plugin\Subscriptions',
            'pdasubscriptions' => 'IxTheo\Controller\Plugin\PDASubscriptions',
        ]
    ],
    'service_manager' => [
        'factories' => [
            'VuFind\Mailer' => 'IxTheo\Mailer\Factory',
        ],
    ],
];

$recordRoutes = [
    // needs to be registered again even if already registered in parent module,
    // for the nonTabRecordActions added in \IxTheo\Route\RouteGenerator
    'record' => 'Record',
];
$dynamicRoutes = [];
$staticRoutes = [
    'Browse/IxTheo-Classification',
    'Browse/Publisher',
    'Browse/RelBib-Classification',
    'Biblerangesearch/Home',
    'Keywordchainsearch/Home',
    'Keywordchainsearch/Results',
    'Keywordchainsearch/Search',
    'MyResearch/Subscriptions',
    'MyResearch/DeleteSubscription',
    'MyResearch/PDASubscriptions',
    'MyResearch/DeletePDASubscription',
    'Pipeline/Home',
];

$config['router']['routes']['static-page'] = [
    'type'    => 'Zend\Mvc\Router\Http\Segment',
    'options' => [
        'route'    => "/:page",
        'constraints' => [
            'page'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        ],
        'defaults' => [
            'controller' => 'StaticPage',
            'action'     => 'staticPage',
        ]
    ]
];

$routeGenerator = new \IxTheo\Route\RouteGenerator();
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

return $config;
