<?php
namespace TueFindSearch\Backend\Solr;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;


class QueryBuilder extends \VuFindSearch\Backend\Solr\QueryBuilder {

    const FULLTEXT_ONLY = 'FulltextOnly';
    const FULLTEXT_WITH_SYNONYMS_HANDLER = 'FulltextWithSynonyms';
    const FULLTEXT_ALL_SYNONYMS_HANDLER = 'FulltextAllSynonyms';
    const FULLTEXT_ONE_LANGUAGE_SYNONYMS_FIELD = 'fulltext_synonyms'; // Language selection is done by SOLR itself
    const FULLTEXT_ALL_LANGUAGE_SYNONYMS_FIELD = 'fulltext_synonyms_all';
    const FULLTEXT_TOC_ONE_LANGUAGE_SYNONYMS_FIELD = 'fulltext_toc_synonyms'; // Language selection is done by SOLR itself
    const FULLTEXT_TOC_ALL_LANGUAGE_SYNONYMS_FIELD = 'fulltext_toc_synonyms_all';
    const FULLTEXT_ABSTRACT_ONE_LANGUAGE_SYNONYMS_FIELD = 'fulltext_abstract_synonyms'; // Language selection is done by SOLR itself
    const FULLTEXT_ABSTRACT_ALL_LANGUAGE_SYNONYMS_FIELD = 'fulltext_abstract_synonyms_all';
    const FULLTEXT_TYPE_FULLTEXT = "Fulltext";
    const FULLTEXT_TYPE_ABSTRACT = "Abstract";
    const FULLTEXT_TYPE_TOC = "Table of Contents";
    protected $createExplainQuery = false;
    protected $selectedFulltextTypes = [];


    public function setCreateExplainQuery($enable)
    {
        $this->createExplainQuery = $enable;
    }


    public function setSelectedFulltextTypes($selected_fulltext_types) {
        $this->selectedFulltextType = $selected_fulltext_types;
    }


    protected function useSynonyms($handler)
    {
        switch ($handler) {
            case self::FULLTEXT_WITH_SYNONYMS_HANDLER:
                return true;
            case self::FULLTEXT_ALL_SYNONYMS_HANDLER:
                return true;
         }
         return false;
    }


    protected function getSynonymQueryField($handler, $fulltext_type)
    {
        switch ($handler) {
            case self::FULLTEXT_WITH_SYNONYMS_HANDLER: {
                switch ($fulltext_type) {
                    case self::FULLTEXT_TYPE_FULLTEXT:
                         return self::FULLTEXT_ONE_LANGUAGE_SYNONYMS_FIELD;
                    case self::FULLTEXT_TYPE_TOC:
                        return self::FULLTEXT_TOC_ONE_LANGUAGE_SYNONYMS_FIELD;
                    case self::FULLTEXT_TYPE_ABSTRACT:
                        return self::FULLTEXT_ABSTRACT_ONE_LANGUAGE_SYNONYMS_FIELD;
                    break;
                }
            }
            case self::FULLTEXT_ALL_SYNONYMS_HANDLER: {
                switch ($fulltext_type) {
                     case self::FULLTEXT_TYPE_FULLTEXT:
                        return self::FULLTEXT_ALL_LANGUAGE_SYNONYMS_FIELD;
                     case self::FULLTEXT_TYPE_TOC:
                         return self::FULLTEXT_TOC_ALL_LANGUAGE_SYNONYMS_FIELD;
                     case self::FULLTEXT_TYPE_ABSTRACT:
                         return self::FULLTEXT_ABSTRACT_ALL_LANGUAGE_SYNONYMS_FIELD;
                     break;
                }
            }
        }
        return "";
    }


    protected function getSynonymsPartialExpressionOrEmpty($search_handler, $query_terms, $previous_expression_empty) {
       $synonyms_expression = "";
       if (empty($this->selectedFulltextTypes) || in_array(self::FULLTEXT_TYPE_FULLTEXT, $this->selectedFulltextType)) {
           $synonyms_expression .=  $this->useSynonyms($search_handler)
                                    ? ($previous_expression_empty ?  '' : ' OR ') .
                                      $this->getSynonymQueryField($search_handler, self::FULLTEXT_TYPE_FULLTEXT) . ':' . $query_terms
                                      : '';
           $previous_expression_empty = false;
       }
       if (empty($this->selectedFulltextTypes) || in_array(self::FULLTEXT_TYPE_TOC, $this->selectedFulltextType)) {
           $synonyms_expression .=  $this->useSynonyms($search_handler)
                                    ? ($previous_expression_empty ? '' : ' OR ') .
                                      $this->getSynonymQueryField($search_handler, self::FULLTEXT_TYPE_TOC) . ':' . $query_terms
                                      : '';
           $previous_expression_empty = false;
       }
       if (empty($this->selectedFulltextTypes) || in_array(self::FULLTEXT_TYPE_ABSTRACT, $this->selectedFulltextType)) {
           $synonyms_expression .=  $this->useSynonyms($search_handler)
                                    ? ($previous_expression_empty ? '' : ' OR ') .
                                    $this->getSynonymQueryField($search_handler, self::FULLTEXT_TYPE_ABSTRACT) . ':' . $query_terms
                                    : '';
           $previous_expression_empty = false;
       }
       return $synonyms_expression;
    }


    protected function getHandler($query) {
        if ($query instanceof \VuFindSearch\Query\Query)
            return $query->getHandler();
        if ($query instanceof \VuFindSearch\Query\QueryGroup)
            return $query->getReducedHandler();
        return "";
    }


    protected function getFulltextExplainOtherQuery($query) {
         $query_terms =  $this->getLuceneHelper()->extractSearchTerms($query->getAllTerms());
         if (!empty($query_terms) && !($this->getLuceneHelper()->containsRanges($query->getAllTerms()))) {
              $query_terms_normalized = \TueFind\Utility::isSurroundedByQuotes($query_terms) ?
                                             $query_terms : '(' . $query_terms . ')';
              $explain_query = "";
              if (empty($this->selectedFulltextType) || in_array(self::FULLTEXT_TYPE_FULLTEXT, $this->selectedFulltextType))
                  $explain_query =  empty($explain_query) ? '' : ' OR' .
                                    'fulltext:' . $query_terms_normalized .
                                    ' OR fulltext_unstemmed:' . $query_terms_normalized;
              if (empty($this->selectedFulltextType) || in_array(self::FULLTEXT_TYPE_TOC, $this->selectedFulltextType))
                  $explain_query .= empty($explain_query) ? '' : ' OR' .
                                    'fulltext_toc:' . $query_terms_normalized .
                                    ' OR fulltext_toc_unstemmed:' . $query_terms_normalized;
              if (empty($this->selectedFulltextType) || in_array(self::FULLTEXT_TYPE_ABSTRACT, $this->selectedFulltextType))
                  $explain_query .= empty($explain_query) ? '' : ' OR' .
                                    'fulltext_abstract:' . $query_terms_normalized .
                                    ' OR fulltext_abstract_unstemmed:' . $query_terms_normalized;
              $explain_query .= $this->getSynonymsPartialExpressionOrEmpty($this->getHandler($query),
                                                                           $query_terms_normalized, empty($explain_query));
              return $explain_query;
         }
         return "";
    }


    public function build(AbstractQuery $query)
    {
        $params = parent::build($query);
        if ($this->createExplainQuery) {
            $fulltext_explain_other_query = $this->getFulltextExplainOtherQuery($query);
            if (!empty($fulltext_explain_other_query))
                $params->set('explainOther', $fulltext_explain_other_query);
        }
        return $params;
    }
}
