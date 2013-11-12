<?php
class Acme_FactFinder_Model_Resource_Product_New_Collection extends Mage_Catalog_Model_Resource_Product_Collection
{
    /**
     * get Factfinder Facade
     *
     * @return Flagbit_FactFinder_Model_Handler_Search
     */
    protected function _getNewProductsHandler()
    {
        return Mage::getSingleton('acme_factfinder/handler_newProducts');
    }

    protected function _beforeLoad()
    {
        parent::_beforeLoad();

        // get product IDs from Fact-Finder
        $productIds = $this->_getNewProductsHandler()->getSearchResult();

        $this->addAttributeToFilter('entity_id', array('in' => array_keys($productIds)));
    }
}