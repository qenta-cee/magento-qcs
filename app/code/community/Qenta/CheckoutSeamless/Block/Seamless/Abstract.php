<?php
/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Qenta Payment CEE GmbH
 * (abbreviated to Qenta CEE) and are explicitly not part of the Qenta CEE range of
 * products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License Version 2 (GPLv2) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Qenta CEE does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Qenta CEE does not guarantee their full
 * functionality neither does Qenta CEE assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Qenta CEE does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 *
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 */

class Qenta_CheckoutSeamless_Block_Seamless_Abstract extends Mage_Core_Block_Template
{
    /**
     * Get the Payment data if stored in the Session
     *
     * @return mixed
     */
    public function getSessionData()
    {
        $data = Mage::getSingleton('core/session')->getQentaCheckoutSeamlessPaymentInfo();
        if ($data) {
            return $data;
        }
        return false;
    }

    public function getFinancialInstitutions()
    {
        /** @var Qenta_CheckoutSeamless_Helper_Data $helper */
        $helper = Mage::helper('qenta_checkoutseamless');

        $cl = new QentaCEE_QMore_BackendClient($helper->getBackendConfigArray());

        $response = $cl->getFinancialInstitutions($this->getMethod()->getPaymentMethodType());
        if (!$response->hasFailed()) {
            $ret = $response->getFinancialInstitutions();
            $c = null;
            if (class_exists('Collator')) {
                $c = new Collator('root');
            }

            uasort($ret, function ($a, $b) use ($c) {
                if ($c === null) {
                    return strcmp($a['id'], $b['id']);
                }
                else {
                    return $c->compare($a['name'], $b['name']);
                }
            });

            return $ret;
        }
        else {
            $helper->log(__METHOD__ . ':' . print_r($response->getErrors(), true), LOG_WARNING);

            return Array();
        }
    }

}