<?php
class Acme_FactFinder_Block_Product_List_New extends Mage_Catalog_Block_Product_List
{
    protected function _getProductCollection()
    {
        if (is_null($this->_productCollection)) {
            $this->_productCollection = Mage::getResourceModel('acme_factfinder/product_new_collection');
        }

        return $this->_productCollection;
    }
}