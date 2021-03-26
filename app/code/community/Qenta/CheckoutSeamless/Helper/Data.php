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

class Qenta_CheckoutSeamless_Helper_Data extends Mage_Payment_Helper_Data
{

    protected $_pluginVersion = '4.2.8';
    protected $_pluginName = 'Qenta/CheckoutSeamless';

    /**
     * predefined test/demo accounts
     *
     * @var array
     */
    protected $_presets = array(
        'demo'      => array(
            'settings/customer_id' => 'D200001',
            'settings/shop_id'     => 'seamless',
            'settings/secret'      => 'B8AKTPWBRMNBV455FG6M2DANE99WU2',
            'settings/backendpw'   => 'jcv45z'
        ),
        'test_no3d' => array(
            'settings/customer_id' => 'D200411',
            'settings/shop_id'     => 'seamless',
            'settings/secret'      => 'CHCSH7UGHVVX2P7EHDHSY4T2S4CGYK4QBE4M5YUUG2ND5BEZWNRZW5EJYVJQ',
            'settings/backendpw'   => '2g4f9q2m'
        ),
        'test_3d'   => array(
            'settings/customer_id' => 'D200411',
            'settings/shop_id'     => 'seamless3D',
            'settings/secret'      => 'DP4TMTPQQWFJW34647RM798E9A5X7E8ATP462Z4VGZK53YEJ3JWXS98B9P4F',
            'settings/backendpw'   => '2g4f9q2m'
        )
    );

    public function getConfigArray()
    {
        $cfg                = Array('LANGUAGE' => $this->getLanguage());
        $cfg['CUSTOMER_ID'] = $this->getConfigData('settings/customer_id');
        $cfg['SHOP_ID']     = $this->getConfigData('settings/shop_id');
        $cfg['SECRET']      = $this->getConfigData('settings/secret');

        return $cfg;
    }

    /**
     * return config array to be used for client lib, backend ops
     *
     * @return array
     */
    public function getBackendConfigArray()
    {
        $cfg             = $this->getConfigArray();
        $cfg['PASSWORD'] = $this->getConfigData('settings/backendpw');

        return $cfg;
    }

    public function getConfigData($field = null, $storeId = null)
    {
        $type =  Mage::getStoreConfig('qenta_checkoutseamless/settings/configuration', $storeId);

        if (isset($this->_presets[$type]) && isset($this->_presets[$type][$field])) {
            return $this->_presets[$type][$field];
        }

        $path = 'qenta_checkoutseamless';
        if ($field !== null) {
            $path .= '/' . $field;
        }

        return Mage::getStoreConfig($path, $storeId);
    }

    /**
     * returns config preformated as string, used in support email
     *
     * @return string
     */
    public function getConfigString()
    {
        $ret     = '';
        $exclude = array('secret', 'backendpw');
        foreach ($this->getConfigData() as $group => $fields) {
            foreach ($fields as $field => $value) {
                if (in_array($field, $exclude)) {
                    continue;
                }
                if (strlen($ret)) {
                    $ret .= "\n";
                }
                $ret .= sprintf("%s: %s", $field, $value);
            }
        }

        return $ret;
    }

    public function getLanguage()
    {
        $locale = explode('_', Mage::app()->getLocale()->getLocaleCode());
        if (is_array($locale) && ! empty( $locale )) {
            $locale = $locale[0];
        } else {
            $locale = $this->getDefaultLocale();
        }

        return $locale;
    }

    public function getPluginVersion()
    {
        return QentaCEE_QMore_FrontendClient::generatePluginVersion('Magento', Mage::getVersion(),
            $this->_pluginName, $this->_pluginVersion);
    }

    public function log($message, $level = null)
    {
        if ($level === null) {
            $level = Zend_Log::INFO;
        }

        Mage::log($message, $level, 'qenta_checkoutseamless.log', true);
    }

    /**
     * @return bool|null|QentaCEE_QMore_DataStorage_Response_Initiation
     */
    public function initDatastorage()
    {
        $dataStorageInit = new QentaCEE_QMore_DataStorageClient($this->getConfigArray());

        $dataStorageInit->setReturnUrl(Mage::getUrl('qenta_checkoutseamless/processing/storereturn',
            array('_secure' => true)));
        $dataStorageInit->setOrderIdent(Mage::getSingleton('checkout/session')->getQuote()->getId());

        $response = null;
        if ($this->getConfigData('ccard/pci3_dss_saq_a_enable')) {
            $dataStorageInit->setJavascriptScriptVersion('pci3');

            if (strlen(trim($this->getConfigData('ccard/iframe_css_url')))) {
                $dataStorageInit->setIframeCssUrl(trim($this->getConfigData('ccard/iframe_css_url')));
            }

            $dataStorageInit->setCreditCardShowCardholderNameField($this->getConfigData('ccard/showcardholder'));
            $dataStorageInit->setCreditCardShowCvcField($this->getConfigData('ccard/showcvc'));
            $dataStorageInit->setCreditCardShowIssueDateField($this->getConfigData('ccard/showissuedate'));
            $dataStorageInit->setCreditCardShowIssueNumberField($this->getConfigData('ccard/showissuenumber'));
        }

        $this->log(__METHOD__ . ':' . print_r($dataStorageInit->getRequestData(), true), Zend_Log::INFO);

        try {
            $response = $dataStorageInit->initiate();
            if ($response->getStatus() == QentaCEE_QMore_DataStorage_Response_Initiation::STATE_SUCCESS) {

                Mage::getSingleton('checkout/session')->setQentaCheckoutSeamlessStorageId($response->getStorageId());
                $this->log(__METHOD__ . ':storageid:' . $response->getStorageId(), Zend_Log::DEBUG);

                return $response;

            } else {

                $dsErrors = $response->getErrors();

                foreach ($dsErrors as $error) {
                    $this->log(__METHOD__ . ':' . $error->getMessage());
                }

                return false;
            }
        } catch (Exception $e) {

            //communication with dataStorage failed. we choose a none dataStorage fallback
            $this->log(__METHOD__ . ':' . $e->getMessage(), Zend_Log::ERR);

            return false;
        }
    }

    /**
     * @return bool|QentaCEE_QMore_DataStorage_Response_Read
     */
    public function readDatastorage()
    {
        $session = Mage::getSingleton('checkout/session');
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($session->getLastRealOrderId());

        $dataStorageRead = new QentaCEE_QMore_DataStorageClient($this->getConfigArray());
        $dataStorageRead->setStorageId(Mage::getSingleton('checkout/session')->getQentaCheckoutSeamlessStorageId());
        $dataStorageRead->read();

        try {

            $response = $dataStorageRead->read();

            if ($response->getStatus() != QentaCEE_QMore_DataStorage_Response_Read::STATE_FAILURE) {

                return $response;

            } else {

                $dsErrors = $response->getErrors();

                foreach ($dsErrors as $error) {
                    $this->log(__METHOD__ . ':' . $error->getMessage(), Zend_Log::ERR);
                }

                return false;
            }
        } catch (Exception $e) {

            //communication with dataStorage failed. we choose a none dataStorage fallback
            Mage::logException($e);

            return false;
        }
    }

}
