<?php
return [
    'extends' => 'tuefind',
    'css' => [
        'compiled.css',
        'feedback.css',
        'vendor/jquery.feedback_me.css',
    ],
    'helpers' => [
        'factories' => [
            'IxTheo\View\Helper\Root\Browse' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'IxTheo\View\Helper\Root\Citation' => 'IxTheo\View\Helper\Root\CitationFactory',
            'IxTheo\View\Helper\Root\Record' => 'IxTheo\View\Helper\Root\RecordFactory',
            'IxTheo\View\Helper\Root\RecordDataFormatter' => 'IxTheo\View\Helper\Root\RecordDataFormatterFactory',
            'IxTheo\View\Helper\IxTheo\IxTheo' => 'IxTheo\View\Helper\IxTheo\Factory',
        ],
        'aliases' => [
            'browse' => 'IxTheo\View\Helper\Root\Browse',
            'citation' => 'IxTheo\View\Helper\Root\Citation',
            'record' => 'IxTheo\View\Helper\Root\Record',
            'recordDataFormatter' => 'IxTheo\View\Helper\Root\RecordDataFormatter',
            'ixtheo' => 'IxTheo\View\Helper\IxTheo\IxTheo',
            'IxTheo' => 'IxTheo\View\Helper\IxTheo\IxTheo',
        ],
    ],
    'js' => [
        'ixtheo.js',
    ],
];
