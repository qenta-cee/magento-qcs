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

class Qenta_CheckoutSeamless_Model_Admin_Test extends Mage_Core_Model_Abstract
{

    public function testconfig()
    {
        /** @var Qenta_CheckoutSeamless_Helper_Data $helper */
        $helper = Mage::helper('qenta_checkoutseamless');
        $returnUrl = Mage::getUrl('qenta_checkoutseamless/processing/storereturn', array('_secure' => true, '_nosid' => true));

        $dataStorageInit = new QentaCEE_QMore_DataStorageClient($helper->getConfigArray());
        $dataStorageInit->setReturnUrl($returnUrl);
        $dataStorageInit->setOrderIdent(session_id());

        try
        {
            $response = $dataStorageInit->initiate();
            if ($response->getStatus() != QentaCEE_QMore_DataStorage_Response_Initiation::STATE_SUCCESS)
            {
                $msg = array();
                foreach ($response->getErrors() as $error)
                {
                    $msg[] = $error->getConsumerMessage();
                }
                Mage::getSingleton('core/session')->addError(implode("<br/>\n", $msg));
                return false;
            }
        } catch (Exception $e)
        {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            return false;
        }

        Mage::getSingleton('core/session')->addSuccess($helper->__('Configuration test ok'));
        return true;
    }
}