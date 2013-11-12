<?php
class Acme_FactFinder_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @param $from
     * @param $to
     * @return bool
     */
    protected function _checkDate($from, $to)
    {
        $today = strtotime(
            Mage::app()->getLocale()->date()
                ->setTime('00:00:00')
                ->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
        );

        if ($from && $today < $from) {
            return false;
        }
        if ($to && $today > $to) {
            return false;
        }
        if (!$to && !$from) {
            return false;
        }
        return true;
    }


    /**
     * @param $product
     * @return bool
     */
    public function isNewProduct($dateFrom, $dateTo)
    {
        $from = strtotime($dateFrom);
        $to = strtotime($dateTo);

        return $this->_checkDate($from, $to);
    }
}