<?php
class Acme_FactFinder_Model_Handler_NewProducts extends Flagbit_FactFinder_Model_Handler_Search
{
    protected $_searchResult;

    protected function configureFacade()
    {
        $params = $this->_collectParams();
        $params['filteris_new'] = '1';

        $this->_getFacade()->configureSearchAdapter($params);
    }
}