<?php
namespace TueFind\Search\Solr;

use VuFindSearch\Backend\BackendInterface;

use Zend\EventManager\EventInterface;
use Zend\EventManager\SharedEventManagerInterface;

class InjectFulltextMatchIdsListener
{

    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Is highlighting active?
     *
     * @var bool
     */
    protected $active = false;

    public function __construct(BackendInterface $backend)
    {
       $this->backend = $backend;
    }
   /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(SharedEventManagerInterface $manager)
    {
        $manager->attach('VuFind\Search', 'pre', [$this, 'onSearchPre']);
        $manager->attach('VuFind\Search', 'post', [$this, 'onSearchPost']);
    }


    /**
     * GetSearchHandlerName
     * @return string
     */
    protected function getSearchHandlerName(EventInterface $event) {
        $query = $event->getParam('query');
        if ($query instanceof \VuFindSearch\Query\Query)
            return $query->getHandler();
        if ($query instanceof \VuFindSearch\Query\QueryGroup)
            return $query->getReducedHandler();
        return "";
    }


    /**
     * Set up highlighting parameters.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event) {
        if ($event->getParam('context') != 'search') {
            return $event;
        }
        $backend = $event->getTarget();
        if ($backend === $this->backend) {
            $params = $event->getParam('params');
            if ($params) {
                $searchHandler = $this->getSearchHandlerName($event);
                // Do not use explain on BibleRangeSearch for performance reasons
                $explain = $searchHandler == 'BibleRangeSearch' ? false : true;
                if ($explain == 'true') {
                    $this->active = true;
                    $params->set('debug', 'results');
                    $params->set('debug.explain.structured', 'true');
                    // The search for explainOther is unknown, so it will only be generated in the
                    // QueryBuilder
                    $this->backend->getQueryBuilder()->setCreateExplainQuery(true);
                }
            }
        }
        return $event;
    }


   /**
     * Inject highlighting results.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPost(EventInterface $event)
    {
        // Do nothing if highlighting is disabled or context is wrong
        if (!$this->active || $event->getParam('context') != 'search') {
            return $event;
        }

        // Inject highlighting details into record objects:
        $backend = $event->getParam('backend');
        if ($backend == $this->backend->getIdentifier()) {
            $result = $event->getTarget();
            $explainOtherIDs = $result->getExplainOther();
            foreach ($result->getRecords() as $record) {
                $id = $record->getUniqueId();
                if (isset($explainOtherIDs[$id])) {
                    $record->setHasFulltextMatch();
                }
            }
        }
    }
}
