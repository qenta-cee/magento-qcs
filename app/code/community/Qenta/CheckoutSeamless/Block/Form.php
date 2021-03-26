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

class Qenta_CheckoutSeamless_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('qenta/checkoutseamless/form.phtml');
    }

    protected function getImageName()
    {
        return preg_replace('/^qenta_checkoutseamless_/', '', $this->getMethodCode());
    }

    public function getMethodLabelAfterHtml()
    {
        $filename = sprintf('images/qenta/checkoutseamless/%s.png', $this->getImageName());
        $filePath = sprintf('%s/frontend/base/default/%s', Mage::getBaseDir('skin'), $filename);
        if (file_exists($filePath)) {
            return sprintf('<img src="%s" title="%s" alt="%s" style="margin-right: 10px;"/>',
                $this->getSkinUrl($filename),
                htmlspecialchars($this->getMethod()->getTitle()),
                htmlspecialchars($this->getMethod()->getTitle()));
        }
        else {
            return '';
        }
    }

    public function hasAdditionalForm()
    {
        return ($this->getAdditionalForm()) ? true : false;
    }

    public function getAdditionalForm()
    {
        $paymentType = strtoupper($this->getMethodCode());
        switch ($paymentType) {
            case 'QENTA_CHECKOUTSEAMLESS_INVOICE':
                return 'qenta_checkoutseamless/additional_Invoice';
                break;
            case 'QENTA_CHECKOUTSEAMLESS_INSTALLMENT':
                return 'qenta_checkoutseamless/additional_Installment';
                break;
            case 'QENTA_CHECKOUTSEAMLESS_INVOICEB2B':
                return 'qenta_checkoutseamless/additional_InvoiceB2b';
                break;
            default:
                return false;
                break;
        }
    }

    public function getSeamlessBlock($paymentType)
    {
        $paymentType = strtoupper(strval($paymentType));
        switch($paymentType)
        {
            case 'CC':
                return 'qenta_checkoutseamless/seamless_Cc';
            case 'CCMOTO':
                return 'qenta_checkoutseamless/seamless_Cc';
            case 'SEPADD':
                return 'qenta_checkoutseamless/seamless_SepaDd';
            case 'PAYBOX':
                return 'qenta_checkoutseamless/seamless_Paybox';
            case 'GIROPAY':
                return 'qenta_checkoutseamless/seamless_Giropay';
            case 'MAESTRO':
                return 'qenta_checkoutseamless/seamless_Cc';
            case 'EPS':
                return 'qenta_checkoutseamless/seamless_Eps';
            case 'TRUSTPAY':
                return 'qenta_checkoutseamless/seamless_Trustpay';
            case 'VOUCHER':
                return 'qenta_checkoutseamless/seamless_Voucher';
            case 'IDEAL':
                return 'qenta_checkoutseamless/seamless_Ideal';
            default:
                return 'qenta_checkoutseamless/seamless_Base';
        }
    }

    public function hasPayolutionTerms()
    {
        if ($this->getMethod()->getConfigData('provider') != 'payolution')
            return false;

        /** @var Qenta_CheckoutSeamless_Helper_Data $helper */
        $helper = Mage::helper('qenta_checkoutseamless');
        return $helper->getConfigData('options/payolution_terms');
    }

    public function getPayolutionLink()
    {
        /** @var Qenta_CheckoutSeamless_Helper_Data $helper */
        $helper = Mage::helper('qenta_checkoutseamless');
        $mId = base64_encode($helper->getConfigData('options/payolution_mid'));

        if (strlen($mId)) {
            return sprintf('<a href="https://payment.payolution.com/payolution-payment/infoport/dataprivacyconsent?mId=%s" style="float: none; margin: 0;" target="_blank">%s</a>',
                $mId, $helper->__('consent'));
        } else {
            return $helper->__('consent');
        }
    }
}