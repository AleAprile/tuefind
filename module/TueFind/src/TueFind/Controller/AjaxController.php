<?php
namespace TueFind\Controller;
use Zend\ServiceManager\ServiceLocatorInterface;

class AjaxController extends \VuFind\Controller\AjaxController
{

   /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        // Add notices to a key in the output
        set_error_handler(['TueFind\Controller\AjaxController', "storeError"]);
        parent::__construct($sm);
    }


   /**
     * Get Subscription Bundle Entries
     *
     * @return \Zend\Http\Response
     */
    protected function getSubscriptionBundleEntriesAjax()
    {
       $query = $this->getRequest()->getQuery();
       $bundle_id = $query->get('bundle_id');
       $this->disableSessionWrites(); // Seems to be needed
       try {
           $results = $this->getResultsManager()->get('Solr');
           $params = $results->getParams();
           $params->getOptions()->spellcheckEnabled(false);
           $params->getOptions()->disableHighlighting();
           $params->setOverrideQuery('bundle_id:' . $bundle_id);
           $result_set = $results->getResults();
           $titles = [];
           foreach ($result_set as $record)
               array_push($titles,  '{ "id" :  "' . $record->getUniqueID() . '" , "title" : "' . $record->getTitle() . '"}');
           return $this->output( '{ "items": [' . implode(', ', $titles)  . '] }', self::STATUS_OK);
        } catch (\Exception $e) {
            return $this->output(
                'Search index error: ' . $e->getMessage(), self::STATUS_ERROR, 500
            );
        }
    }
}
