<?php
class Acme_FactFinder_Model_Export_Product extends Flagbit_FactFinder_Model_Export_Product
{
    /**
     * get CSV Header Array
     *
     * @param int $storeId
     * @return array
     */
    protected function _getExportAttributes($storeId = null)
    {
        if($this->_exportAttributeCodes === null){
            $headerDefault = array('id', 'parent_id', 'sku', 'category', 'filterable_attributes', 'searchable_attributes');
            $headerDynamic = array();

            if (Mage::getStoreConfigFlag('factfinder/export/urls', $storeId)) {
                $headerDefault[] = 'image';
                $headerDefault[] = 'deeplink';
                $this->_imageHelper = Mage::helper('catalog/image');
            }

            $headerDefault[] = 'is_new';

            // get dynamic Attributes
            foreach ($this->_getSearchableAttributes(null, 'system', $storeId) as $attribute) {
                if (in_array($attribute->getAttributeCode(), array('sku', 'status', 'visibility'))) {
                    continue;
                }
                $headerDynamic[] = $attribute->getAttributeCode();
            }

            // compare dynamic with setup attributes
            $headerSetup = Mage::helper('factfinder/backend')->makeArrayFieldValue(Mage::getStoreConfig('factfinder/export/attributes', $storeId));
            $setupUpdate = false;
            foreach($headerDynamic as $code){
                if(in_array($code, $headerSetup)){
                    continue;
                }
                $headerSetup[$code]['attribute'] = $code;
                $setupUpdate = true;
            }

            // remove default attributes from setup
            foreach($headerDefault as $code){
                if(array_key_exists($code, $headerSetup)){
                    unset($headerSetup[$code]);
                    $setupUpdate = true;
                }
            }

            if($setupUpdate === true){
                Mage::getModel('core/config')->saveConfig('factfinder/export/attributes', Mage::helper('factfinder/backend')->makeStorableArrayFieldValue($headerSetup), 'stores', $storeId);
            }

            $this->_exportAttributeCodes = array_merge($headerDefault, array_keys($headerSetup));
        }
        return $this->_exportAttributeCodes;
    }

    /**
     * export Product Data with Attributes
     * direct Output as CSV
     *
     * @param int $storeId Store View Id
     */
    public function doExport($storeId = null)
    {
        $idFieldName = Mage::helper('factfinder/search')->getIdFieldName();
        $exportImageAndDeeplink = Mage::getStoreConfigFlag('factfinder/export/urls', $storeId);
        if ($exportImageAndDeeplink) {
            $imageType = Mage::getStoreConfig('factfinder/export/suggest_image_type', $storeId);
            $imageSize = (int) Mage::getStoreConfig('factfinder/export/suggest_image_size', $storeId);
        }

        $header = $this->_getExportAttributes($storeId);
        $this->_addCsvRow($header);

        // preparesearchable attributes
        $staticFields   = array();
        foreach ($this->_getSearchableAttributes('static', 'system', $storeId) as $attribute) {
            $staticFields[] = $attribute->getAttributeCode();
        }
        $dynamicFields  = array(
            'int'       => array_keys($this->_getSearchableAttributes('int')),
            'varchar'   => array_keys($this->_getSearchableAttributes('varchar')),
            'text'      => array_keys($this->_getSearchableAttributes('text')),
            'decimal'   => array_keys($this->_getSearchableAttributes('decimal')),
            'datetime'  => array_keys($this->_getSearchableAttributes('datetime')),
        );

        // status and visibility filter
        $visibility     = $this->_getSearchableAttribute('visibility');
        $status         = $this->_getSearchableAttribute('status');
        $visibilityVals = Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds();
        $statusVals     = Mage::getSingleton('catalog/product_status')->getVisibleStatusIds();

        $newFrom        = $this->_getSearchableAttribute('news_from_date');
        $newTo          = $this->_getSearchableAttribute('news_to_date');

        $lastProductId = 0;
        while (true) {
            $products = $this->_getSearchableProducts($storeId, $staticFields, null, $lastProductId);
            if (!$products) {
                break;
            }

            $productRelations   = array();
            foreach ($products as $productData) {
                $lastProductId = $productData['entity_id'];
                $productAttributes[$productData['entity_id']] = $productData['entity_id'];
                $productChilds = $this->_getProductChildIds($productData['entity_id'], $productData['type_id']);
                $productRelations[$productData['entity_id']] = $productChilds;
                if ($productChilds) {
                    foreach ($productChilds as $productChild) {
                        $productAttributes[$productChild['entity_id']] = $productChild;
                    }
                }
            }

            $productAttributes		= $this->_getProductAttributes($storeId, array_keys($productAttributes), $dynamicFields);
            foreach ($products as $productData) {
                if (!isset($productAttributes[$productData['entity_id']])) {
                    continue;
                }
                $protductAttr = $productAttributes[$productData['entity_id']];

                if (!isset($protductAttr[$visibility->getId()]) || !in_array($protductAttr[$visibility->getId()], $visibilityVals)) {
                    continue;
                }
                if (!isset($protductAttr[$status->getId()]) || !in_array($protductAttr[$status->getId()], $statusVals)) {
                    continue;
                }

                $productIndex = array(
                    $productData['entity_id'],
                    $productData[$idFieldName],
                    $productData['sku'],
                    $this->_getCategoryPath($productData['entity_id'], $storeId),
                    $this->_formatFilterableAttributes($this->_getSearchableAttributes(null, 'filterable'), $protductAttr, $storeId),
                    $this->_formatSearchableAttributes($this->_getSearchableAttributes(null, 'searchable'), $protductAttr, $storeId)
                );

                if ($exportImageAndDeeplink) {
                    $product = Mage::getModel("catalog/product");
                    $product->setStoreId($storeId);
                    $product->load($productData['entity_id']);

                    $productIndex[] = (string) $this->_imageHelper->init($product, $imageType)->resize($imageSize);
                    $productIndex[] = $product->getProductUrl();
                }

                if(isset($protductAttr[$newFrom->getId()]) && isset($protductAttr[$newTo->getId()])) {
                    // Both dates are set, check if date range is valid
                    $productIndex[] = (string) Mage::helper('acme_factfinder')->isNewProduct($protductAttr[$newFrom->getId()], $protductAttr[$newTo->getId()]);
                } else {
                    // No dates are set
                    $productIndex[] = '0';
                }

                $this->_getAttributesRowArray($productIndex, $protductAttr, $storeId);

                $this->_addCsvRow($productIndex);

                if ($productChilds = $productRelations[$productData['entity_id']]) {
                    foreach ($productChilds as $productChild) {
                        if (isset($productAttributes[$productChild['entity_id']])) {

                            $subProductIndex = array(
                                $productChild['entity_id'],
                                $productData[$idFieldName],
                                $productChild['sku'],
                                $this->_getCategoryPath($productData['entity_id'], $storeId),
                                $this->_formatFilterableAttributes($this->_getSearchableAttributes(null, 'filterable'), $productAttributes[$productChild['entity_id']], $storeId),
                                $this->_formatSearchableAttributes($this->_getSearchableAttributes(null, 'searchable'), $productAttributes[$productChild['entity_id']], $storeId)
                            );
                            if ($exportImageAndDeeplink) {
                                //dont need to add image and deeplink to child product, just add empty values
                                $subProductIndex[] = '';
                                $subProductIndex[] = '';
                            }

                            if(isset($productAttributes[$productChild['entity_id']][$newFrom->getId()]) && isset($productAttributes[$productChild['entity_id']][$newTo->getId()])) {
                                // Both dates are set, check if date range is valid
                                $subProductIndex[] = (string) Mage::helper('acme_factfinder')->isNewProduct(
                                    $productAttributes[$productChild['entity_id']][$newFrom->getId()],
                                    $productAttributes[$productChild['entity_id']][$newTo->getId()]
                                );
                            } else {
                                // No dates are set
                                $subProductIndex[] = '0';
                            }

                            $this->_getAttributesRowArray($subProductIndex, $productAttributes[$productChild['entity_id']], $storeId);

                            $this->_addCsvRow($subProductIndex);
                        }
                    }
                }
            }

            unset($products);
            unset($productAttributes);
            unset($productRelations);
            flush();
        }
    }
}