<?php
/**
 * Proxy Controller Module
 *
 * @category    TueFind
 * @author      Johannes Riedl <johannes.riedl@uni-tuebingen.de>
 * @copyright   2019 Universtitätsbibliothek Tübingen
 */
namespace TueFind\Controller;

use VuFind\Exception\Forbidden as ForbiddenException;
use Elasticsearch\ClientBuilder;
use \Exception as Exception;
use Zend\Log\Logger as Logger;
use Zend\View\Model\JsonModel;


/**
 * Proxy for Fulltext Snippets in Elasticsearch
 * @package  Controller
 */

class FulltextSnippetProxyController extends \VuFind\Controller\AbstractBase
{

    protected $base_url; //Elasticsearch host and port (host:port)
    protected $index; //Elasticsearch index
    protected $es; // Elasticsearch interface
    protected $logger;
    protected $configLoader;
    const FIELD = 'full_text';
    const DOCUMENT_ID = 'id';
    const highlightStartTag = '<span class="highlight">';
    const highlightEndTag = '</span>';
    const fulltextsnippetIni = 'fulltextsnippet';
    const MAX_SNIPPETS = 5;


    public function __construct(\Elasticsearch\ClientBuilder $builder, \VuFind\Log\Logger $logger, \VuFind\Config\PluginManager $configLoader) {
        $this->logger = $logger;
        $this->configLoader = $configLoader;
        $config = $configLoader->get($this->getFulltextSnippetIni());
        $this->base_url = isset($config->Elasticsearch->base_url) ? $config->Elasticsearch->base_url : 'localhost:9200';
        $this->index = isset($config->Elasticsearch->index) ? $config->Elasticsearch->index : 'full_text_cache';
        $this->es = $builder::create()->setHosts([$this->base_url])->build();
    }


    protected function getFulltextSnippetIni() {
        return self::fulltextsnippetIni;
    }


    protected function getFulltext($doc_id, $search_query, $verbose) {
        // Is this an ordinary query or a phrase query (surrounded by quotes) ?
        $is_phrase_query = \TueFind\Utility::isSurroundedByQuotes($search_query);
        $params = [
             'index' => $this->index,
             'type' => '_doc',
             'body' => [
                 '_source' => false,
                 'size' => '100',
                 'query' => [
                     'bool' => [
                           'must' => [
                               [ $is_phrase_query ? 'match_phrase' : 'match' => [ self::FIELD => $search_query ] ],
                               [ 'match' => [ self::DOCUMENT_ID => $doc_id ] ]
                           ]
                      ]
                 ],
                 'highlight' => [
                     'fields' => [
                          self::FIELD => [
                               'fragment_size' => $verbose ? '700' : '300',
                               'phrase_limit' => '3'
                           ]
                      ]
                 ]
             ]
        ];

        $response = $this->es->search($params);
        return $this->extractSnippets($response);
    }


    protected function extractSnippets($response) {
        $top_level_hits = [];
        $hits = [];
        $highlight_results = [];
        if (empty($response))
            return false;
        if (array_key_exists('hits', $response))
            $top_level_hits = $response['hits'];
        if (empty($top_level_hits))
            return false;
        //second order hits
        if (array_key_exists('hits', $top_level_hits))
            $hits = $top_level_hits['hits'];
        if (empty($top_level_hits))
            return false;

        $snippets = [];
        if (count($hits) > self::MAX_SNIPPETS)
            $hits = array_slice($hits, 0, self::MAX_SNIPPETS);
        error_log("HITS:" . count($hits));
        foreach ($hits as $hit) {
            if (array_key_exists('highlight', $hit))
                $highlight_results = $hit['highlight'][self::FIELD];
            if (count($highlight_results) > self::MAX_SNIPPETS);
                $highlight_results = array_slice($highlight_results, 0, self::MAX_SNIPPETS);
            foreach ($highlight_results as $highlight_result) {
                array_push($snippets, $highlight_result);
            }
        }
        return empty($snippets) ? false : $this->formatHighlighting($snippets);
    }


    protected function formatHighlighting($snippets) {
        $formatted_snippets = [];
        foreach ($snippets as $snippet) {
            $snippet = '...' . $snippet . '...';
            array_push($formatted_snippets, str_replace(['<em>', '</em>'], [self::highlightStartTag, self::highlightEndTag], $snippet));
        }
        return $formatted_snippets;
    }


    public function loadAction() : JsonModel
    {
        $query = $this->getRequest()->getUri()->getQuery();
        $parameters = [];
        parse_str($query, $parameters);
        $doc_id = $parameters['doc_id'];
        if (empty($doc_id))
            return new JsonModel([
               'status' => 'EMPTY DOC_ID'
                ]);
        $search_query = $parameters['search_query'];
        if (empty($search_query))
            return new JsonModel([
                'status' => 'EMPTY QUERY'
                ]);
        $verbose = isset($parameters['verbose']) && $parameters['verbose'] == '1' ? true : false;
        $snippets = $this->getFulltext($doc_id, $search_query, $verbose);
        if (empty($snippets))
            return new JsonModel([
                 'status' => 'NO RESULTS'
                ]);

        return new JsonModel([
               'status' => 'SUCCESS',
               'snippets' =>  $snippets
               ]);
    }
}
