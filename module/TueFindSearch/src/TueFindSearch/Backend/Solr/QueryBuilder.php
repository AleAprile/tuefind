<?php
namespace TueFindSearch\Backend\Solr;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;

use VuFindSearch\Query\QueryGroup;


class QueryBuilder extends \VuFindSearch\Backend\Solr\QueryBuilder {
    protected $createExplainQuery = true;

    public function setCreateExplainQuery($enable)
    {
        $this->createExplainQuery = true;
    }

    public function build(AbstractQuery $query) {
        $params = parent::build($query);
        if ($this->createExplainQuery) {
            $query_terms =  $this->getLuceneHelper()->extractSearchTerms($query->getAllTerms());
            $params->set('explainOther', 'fulltext:' . $query_terms);
        }
        return $params;
    }
         

}
?>
