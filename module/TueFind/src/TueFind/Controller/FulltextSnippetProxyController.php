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

class FulltextSnippetProxyController extends \VuFind\Controller\AbstractBase implements \VuFind\I18n\Translator\TranslatorAwareInterface
{

    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    protected $base_url; //Elasticsearch host and port (host:port)
    protected $index; //Elasticsearch index
    protected $page_index; //Elasticssearch index with single HTML pages
    protected $es; // Elasticsearch interface
    protected $logger;
    protected $configLoader;
    protected $maxSnippets = 5;
    const FIELD = 'full_text';
    const DOCUMENT_ID = 'id';
    const highlightStartTag = '<span class="highlight">';
    const highlightEndTag = '</span>';
    const fulltextsnippetIni = 'fulltextsnippet';
    const MAX_SNIPPETS_DEFAULT = 3;
    const MAX_SNIPPETS_VERBOSE = 1000;
    const PHRASE_LIMIT = 10000000;
    const FRAGMENT_SIZE_DEFAULT = 300;
    const FRAGMENT_SIZE_VERBOSE = 700;
    const ORDER_DEFAULT = 'none';
    const ORDER_VERBOSE = 'score';
    const esHighlightTag = 'em';



    public function __construct(\Elasticsearch\ClientBuilder $builder, \Zend\ServiceManager\ServiceLocatorInterface $sm, \VuFind\Log\Logger $logger, \VuFind\Config\PluginManager $configLoader) {
        $this->logger = $logger;
        $this->configLoader = $configLoader;
        $config = $configLoader->get($this->getFulltextSnippetIni());
        $this->base_url = isset($config->Elasticsearch->base_url) ? $config->Elasticsearch->base_url : 'localhost:9200';
        $this->index = isset($config->Elasticsearch->index) ? $config->Elasticsearch->index : 'full_text_cache';
        $this->page_index = isset($config->Elasticsearch->page_index) ? $config->Elasticsearch->page_index : 'full_text_cache_html';
        $this->es = $builder::create()->setHosts([$this->base_url])->build();
        parent::__construct($sm);
    }


    protected function getFulltextSnippetIni() {
        return self::fulltextsnippetIni;
    }


    protected function selectSynonymAnalyzer($synonyms) {
        if ($synonyms == "all")
            return 'synonyms_all';
        if ($synonyms == "lang") {
            $this->setTranslator($this->serviceLocator->get('Zend\Mvc\I18n\Translator'));
            $current_lang = $this->getTranslatorLocale();
            $analyzer = 'synonyms_' . $current_lang;
            return $analyzer;
        }
        return 'fulltext_analyzer';
    }


    protected function getQueryParams($doc_id, $search_query, $verbose, $synonyms, $paged_results) {
        $is_phrase_query = \TueFind\Utility::isSurroundedByQuotes($search_query);
        $this->maxSnippets = $verbose ? self::MAX_SNIPPETS_VERBOSE : self::MAX_SNIPPETS_DEFAULT;
        $index = $paged_results ? $this->page_index : $this->index;
        $synonym_analyzer = $this->selectSynonymAnalyzer($synonyms);

        $params = [
            'index' => $index,
            'body' => [
                '_source' => $paged_results ? [ "page", "full_text", "id" ] : false,
                'size' => '100',
                'sort' => $paged_results && $verbose ? [ 'page' => 'asc' ] : [ '_score' ],
                'query' => [
                    'bool' => [
                        'must' => [
                            [ $is_phrase_query ? 'match_phrase' : 'match' => [
                                                                               self::FIELD => [ 'query' => $search_query,
                                                                                                'analyzer' => $synonym_analyzer ]
                                                                             ],
                            ],
                            [ 'match' => [ self::DOCUMENT_ID => $doc_id ] ]
                         ]
                    ]
                ],
                'highlight' => [
                    'fields' => [
                        self::FIELD => [
                            'type' => 'unified',
                            'fragment_size' => $verbose ? self::FRAGMENT_SIZE_VERBOSE : self::FRAGMENT_SIZE_DEFAULT,
                            'phrase_limit' => self::PHRASE_LIMIT,
                            'number_of_fragments' => $paged_results ? 0 : $this->maxSnippets, /* For oriented approach get whole page */
                            'order' => $verbose ? self::ORDER_VERBOSE : self::ORDER_DEFAULT,
                        ]
                    ]
                ]
            ]
        ];
        return $params;
    }


    protected function getFulltext($doc_id, $search_query, $verbose, $synonyms) {
        // Is this an ordinary query or a phrase query (surrounded by quotes) ?
        $params = $this->getQueryParams($doc_id, $search_query, $verbose,
                                        $synonyms , false /*return paged results*/);
        $response = $this->es->search($params);
        $snippets = $this->extractSnippets($response);
        if ($snippets == false)
            return false;
        $results['snippets'] = $snippets['snippets'];
        return $results;
    }


    protected function getPagedAndFormattedFulltext($doc_id, $search_query, $verbose, $synonyms) {
        $params = $this->getQueryParams($doc_id, $search_query, $verbose, $synonyms, true);
        $response = $this->es->search($params);
        $snippets = $this->extractSnippets($response);
        if ($snippets == false)
            return false;
        $results['snippets'] = $snippets['snippets'];
        return $results;
    }


    protected function extractStyle($html_page) {
        $dom = new \DOMDocument();
        $dom->loadHTML($html_page, LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        $style_object = $xpath->query('/html/head/style');
        $style = $dom->saveHTML($style_object->item(0));
        return $style;
    }


    // Needed because each page has its own classes that we finally have to import
    // So try to avoid clashes by prefixing them with id and page
    protected function normalizeCSSClasses($doc_id, $page, $object) {
        // Replace patterns '.ftXXX{' or 'class=\n?"ftXXX"
        $object = preg_replace('/(?<=class="|class=\n"|\.)ft(\d+)(?=[{"])/', '_' . $doc_id . '_' . $page . '_ft\1', $object);
        return $object;
    }


    protected function hasIntersectionWithPreviousEnd($xpath, &$previous_sibling_right, $node, $left_sibling_path, $right_sibling_path) {
        $left_siblings = $xpath->query($left_sibling_path);
        if ($left_siblings->count()) {
            $left_sibling = $left_siblings->item(0);
            if (isset($previous_sibling_right) && $previous_sibling_right && $left_sibling->isSameNode($previous_sibling_right)) {
                $previous_sibling_right = $xpath->query($right_sibling_path)->item(0) ?? false;
                return true;
            }
            $previous_sibling_right = $xpath->query($right_sibling_path)->item(0) ?? false;
        }
        else
            $previous_sibling_right = $node;
        return false;
    }


    protected function assembleSnippet($dom, $node, $left_sibling, $right_sibling, $snippet_tree) {
        if (!is_null($left_sibling)) {
            $import_node_left = $snippet_tree->importNode($left_sibling, true);
            $snippet_tree->appendChild($import_node_left);
        }
        $import_node = $snippet_tree->importNode($node, true /*deep*/);
        $snippet_tree->appendChild($import_node);
        if (!is_null($right_sibling)) {
            $import_node_right = $snippet_tree->importNode($right_sibling, true /*deep*/);
            $snippet_tree->appendChild($import_node_right);
        }
        return $snippet_tree;
    }


    protected function containsHighlightedPart($xpath, $node) {
        return is_null($node) || $node == false ? false : $xpath->query('./' . self::esHighlightTag, $node)->count();
    }


    protected function extractSnippetParagraph($snippet_page) {
        $dom = new \DOMDocument();
        $dom->loadHTML($snippet_page, LIBXML_NOERROR /*Needed since ES highlighting does not address nesting of tags properly*/);
        $dom->normalizeDocument(); //Hopefully get rid of strange empty textfields caused by whitespace nodes that prevent proper navigation
        $xpath = new \DOMXPath($dom);
        $highlight_nodes =  $xpath->query('//' . self::esHighlightTag);
        $snippet_trees = [];
        $previous_highlight_parent_node;
        $previous_sibling_right; // This variable is passed as reference to hasIntersectionWithPreviousEnd and thus transfers status during the iterations
        foreach ($highlight_nodes as $highlight_node) {
            $parent_node = $highlight_node->parentNode;
            if (is_null($parent_node))
                continue;
            $parent_node_path = $parent_node->getNodePath();
            // Make sure we do not get different snippets if we have several highlights in the same paragraph
            if (isset($previous_highlight_parent_node) && $parent_node->isSameNode($previous_highlight_parent_node))
                continue;
            $previous_highlight_parent_node = $parent_node;
            // Make sure we do not get different snippets if the previous right sibling is identical to the current highlight node
            if ($this->containsHighlightedPart($xpath, $previous_sibling_right ?? null) &&
                $this->hasIntersectionWithPreviousEnd($xpath, $previous_sibling_right, $parent_node, $parent_node_path, $parent_node_path))
                continue;
            $left_sibling_path = $parent_node_path . '/preceding-sibling::p[1]';
            $right_sibling_path = $parent_node_path . '/following-sibling::p[1]';
            $left_sibling = $xpath->query($left_sibling_path)->item(0);
            $right_sibling = $xpath->query($right_sibling_path)->item(0);
            $has_intersection = $this->hasIntersectionWithPreviousEnd($xpath,
                                                                      $previous_sibling_right,
                                                                      $parent_node,
                                                                      $left_sibling_path,
                                                                      $right_sibling_path);
            $snippet_tree = $this->assembleSnippet($dom,
                                                   $parent_node,
                                                   $has_intersection ? null : $left_sibling,
                                                   $right_sibling,
                                                   $has_intersection ? array_pop($snippet_trees) : new \DomDocument);

            array_push($snippet_trees, $snippet_tree);
        }

        array_walk($snippet_trees, function($snippet_tree) { $snippet_tree->appendChild($snippet_tree->createTextNode('...'));
                                                             return $snippet_tree; } );
        $snippets_html = array_map(function($snippet_tree) { return $snippet_tree->saveHTML(); }, $snippet_trees );

        return implode("", $snippets_html);

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
        $pages = [];
        if (count($hits) > $this->maxSnippets)
            $hits = array_slice($hits, 0, $this->maxSnippets);
        foreach ($hits as $hit) {
            if (array_key_exists('highlight', $hit))
                $highlight_results = $hit['highlight'][self::FIELD];
            if (count($highlight_results) > $this->maxSnippets);
                $highlight_results = array_slice($highlight_results, 0, $this->maxSnippets);
            foreach ($highlight_results as $highlight_result) {
                // Handle pages or generic highlight snippets accordingly
                if (isset($hit['_source']['page'])) {
                    $doc_id = $hit['_source']['id'];
                    $page = $hit['_source']['page'];
                    $style = $this->extractStyle($hit['_source']['full_text']);
                    $style = $this->normalizeCSSClasses($doc_id, $page, $style);
                    $snippet_page = $this->normalizeCSSClasses($doc_id, $page, $highlight_result);
                    $snippet_page = preg_replace('/(<[^>]+) style=[\\s]*".*?"/i', '$1', $snippet_page); //remove styles with absolute positions
                    $snippet = $this->extractSnippetParagraph($snippet_page);
                    array_push($snippets, [ 'snippet' => $snippet, 'page' => $page, 'style' => $style]);
                } else {
                    array_push($snippets, [ 'snippet' => $highlight_result ]);
                }
            }
        }
        if (empty($snippets))
            return false;

        $results['snippets'] = $this->formatHighlighting($snippets);
        return $results;
    }


    protected function formatHighlighting($snippets) {
        $formatted_snippets = [];
        foreach ($snippets as $snippet)
            array_push($formatted_snippets, str_replace(['<em>', '</em>'], [self::highlightStartTag, self::highlightEndTag], $snippet));
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
        $synonyms = isset($parameters['synonyms']) && preg_match('/lang|all/', $parameters['synonyms']) ? $parameters['synonyms'] : "";
        $snippets = $this->getPagedAndFormattedFulltext($doc_id, $search_query, $verbose, $synonyms);
        if (empty($snippets)) {
            // Use non-paged text as fallback
            $snippets = $this->getFulltext($doc_id, $search_query, $verbose, $synonyms);
            if (empty($snippets)) {
                return new JsonModel([
                     'status' => 'NO RESULTS'
                 ]);
            }
        }

        return new JsonModel([
               'status' => 'SUCCESS',
               'snippets' => $snippets['snippets']
               ]);
    }
}
