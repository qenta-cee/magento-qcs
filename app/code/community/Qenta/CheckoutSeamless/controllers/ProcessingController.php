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

class Qenta_CheckoutSeamless_ProcessingController extends Mage_Core_Controller_Front_Action
{
    protected $paymentInst;

    /** @var  Mage_Sales_Model_Order */
    protected $order;

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * does nothing, checkout is always done within iframe
     */
    public function checkoutAction()
    {

    }

    /**
     * return redirecturl, which will be used as src for the iframe
     */
    public function getRedirectUrlAction()
    {
        $ret = Array('url' => Mage::getSingleton('checkout/session')->getQentaCheckoutSeamlessRedirectUrl());
        /** @var Qenta_CheckoutSeamless_Helper_Data $helper */
        $helper = Mage::helper('qenta_checkoutseamless');
        $helper->log(__METHOD__ . ':' . Mage::getSingleton('checkout/session')->getQentaCheckoutSeamlessRedirectUrl());
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($ret));
    }

    /**
     * Store anonymized Payment Data from Seamless Checkout in the Session
     */
    public function saveSessInfoAction()
    {
        $postData = $this->getRequest()->getPost();

        /** @var Qenta_CheckoutSeamless_Helper_Data $helper */
        $helper = Mage::helper('qenta_checkoutseamless');

        if (!empty($postData) && isset($postData['payment']) && !empty($postData['payment'])) {
            $payment = $postData['payment'];
            if (!$helper->getConfigData('ccard/pci3_dss_saq_a_enable')) {
                if (!empty($payment['cc_owner']) && !empty($payment['cc_type'])
                    && !empty($payment['cc_number']) && !empty($payment['cc_exp_month']) && !empty($payment['cc_exp_year'])
                ) {
                    Mage::getSingleton('core/session')->setQentaCheckoutSeamlessPaymentInfo($payment);
                }
            }
        }

        return;
    }

    /**
     * Read paymentinformation from datastorage
     */
    public function readDatastorageAction()
    {
        /** @var Qenta_CheckoutSeamless_Helper_Data $helper */
        $helper = Mage::helper('qenta_checkoutseamless');
        $payment = $this->getRequest()->getPost();

        $ret = new \stdClass();
        $ret->status = QentaCEE_QMore_DataStorage_Response_Read::STATE_NOT_EXISTING;
        $ret->paymentInformaton = Array();

        if (!empty($payment) && isset($payment['payment']) && !empty($payment['payment'])) {
            $payment = $payment['payment'];

            if ($payment['method'] == 'qenta_checkoutseamless_cc' || $payment['method'] == 'qenta_checkoutseamless_ccMoto') {

                $readResponse = $helper->readDatastorage();
                if ($readResponse) {
                    $ret->status = $readResponse->getStatus();
                    $ret->paymentInformaton = $readResponse->getPaymentInformation();
                }

            }
        }

        print json_encode($ret);

    }


    /**
     * Delete the anonymized Qenta Checkout Page Session Data stored from Seamless Checkout
     */
    public function deleteSessInfoAction()
    {
        if ($this->getRequest()->isPost()) {
            Mage::getSingleton('core/session')->unsQentaCheckoutSeamlessPaymentInfo();
        }
        return;
    }

    /**
     * The controller action used for older browsers to return datastorage parameters in an iFrame.
     */
    public function storereturnAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Qenta Checkout Seamless return action
     */
    public function returnAction()
    {
        /** @var Qenta_CheckoutSeamless_Helper_Data $helper */
        $helper = Mage::helper('qenta_checkoutseamless');
        try {
            if (!$this->getRequest()->isGet())
                throw new Exception('Not a GET message');

            $session = $this->getCheckout();
            $session->setQentaCheckoutSeamlessRedirectUrl(null);

            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order');
            $order->load($session->getLastOrderId());
            if (!$order->getId())
                throw new Exception('Order not found');

            // confirm request has not been processed
            if (!$order->getPayment()->getAdditionalInformation('confirmProcessed')) {
                $msg = $helper->__('An internal error occurred during the payment process!');
                $helper->log(__METHOD__ . ':Confirm via server2server request is not working, check your firewall!');
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $helper->__($msg))->save();
                $order->cancel();
                $payment = $order->getPayment();
                $payment->setAdditionalInformation('consumerMessage', $msg);
                $order->save();
            }

            // the customer has canceled the payment. show cancel message.
            if ($order->isCanceled()) {
                $quoteId = $session->getLastQuoteId();
                if ($quoteId) {
                    $quote = Mage::getModel('sales/quote')->load($quoteId);
                    if ($quote->getId()) {
                        $quote->setIsActive(true)->save();
                        $session->setQuoteId($quoteId);
                    }
                }
                $consumerMessage = $order->getPayment()->getAdditionalInformation('consumerMessage');
                if (!strlen($consumerMessage)) {
                    //fallback message if no consumerMessage has been set
                    $consumerMessage = $helper->__('Order has been canceled.');
                }
                throw new Exception($helper->__($consumerMessage));
            }

            // get sure order status has changed since redirect
            if ($order->getStatus() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW)
                throw new Exception($helper->__('Sorry, your payment has not confirmed by the payment provider.'));

            if ($order->getStatus() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $msg = $helper->__('Your order will be processed as soon as we get the payment confirmation from you bank.');
                Mage::getSingleton('checkout/session')->addNotice($msg);
            }

            $this->getCheckout()->setLastSuccessQuoteId($session->getLastQuoteId());
            $this->getCheckout()->setResponseRedirectUrl('checkout/onepage/success');
        } catch (Exception $e) {
            $helper->log(__METHOD__ . ':' . $e->getMessage(), Zend_Log::ERR);
            $this->getCheckout()->addNotice($e->getMessage());
            $this->getCheckout()->setResponseRedirectUrl('checkout/cart/');
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Process transaction confirm message
     */
    public function confirmAction()
    {
        /** @var Qenta_CheckoutSeamless_Helper_Data $helper */
        $helper = Mage::helper('qenta_checkoutseamless');
        try {

            if (!$this->getRequest()->isPost())
                throw new Exception('Not a POST message');

            $data = $this->getRequest()->getPost();

            $helper->log(__METHOD__ . ':' . print_r($data, true));

            if (!isset($data['mage_orderId']))
                throw new Exception('Magent OrderId is missing');

            $return = QentaCEE_QMore_ReturnFactory::getInstance($data, $helper->getConfigData('settings/secret'));
            if (!$return->validate())
                throw new Exception('Validation error: invalid response');

            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($data['mage_orderId']);
            if (!$order->getId())
                throw new Exception('Order not found with Id:' . $data['mage_orderId']);

            /** @var Qenta_CheckoutSeamless_Model_Abstract $paymentInst */
            $paymentInst = $order->getPayment()->getMethodInstance();
            $paymentInst->setResponse($data);

            switch ($return->getPaymentState()) {
                case QentaCEE_QMore_ReturnFactory::STATE_SUCCESS:
                case QentaCEE_QMore_ReturnFactory::STATE_PENDING:
                    $this->_confirmOrder($order, $return);
                    break;

                case QentaCEE_QMore_ReturnFactory::STATE_CANCEL:
                    /** @var QentaCEE_QMore_Return_Cancel $return */
                    if (!$this->_succeeded($order)) {
                        $msg = array();
                        foreach ($return->getErrors() as $error) {
                            $msg[] = $error->getConsumerMessage();
                        }

                        if (!count($msg)) {
                            // dont show technical error to consumer
                            $message = $helper->__('An error occured during the payment process');
                        }
                        else {
                            $message = implode("<br/>\n", $msg);
                        }

                        $payment = $order->getPayment();
                        $additionalInformation = Array('confirmProcessed' => true, 'consumerMessage' => $message);
                        $payment->setAdditionalInformation($additionalInformation);
                        $payment->setAdditionalData(serialize($additionalInformation));
                        $payment->save();

                        $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, $helper->__('An error occured during the payment process'))->save();
                        $order->cancel();
                    }
                    break;

                case QentaCEE_QMore_ReturnFactory::STATE_FAILURE:
                    /** @var QentaCEE_QMore_Return_Failure $return */
                    if (!$this->_succeeded($order)) {
                        $msg = array();
                        foreach ($return->getErrors() as $error) {
                            $msg[] = $error->getConsumerMessage();
                        }

                        if (!count($msg)) {
                            // dont show technical error to consumer
                            $message = $helper->__('Customer canceled the payment process');
                        }
                        else {
                            $message = implode("<br/>\n", $msg);
                        }

                        $payment = $order->getPayment();
                        $additionalInformation = Array('confirmProcessed' => true, 'consumerMessage' => $message);
                        $payment->setAdditionalInformation($additionalInformation);
                        $payment->setAdditionalData(serialize($additionalInformation));
                        $payment->save();

                        $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, $helper->__('An error occured during the payment process'))->save();
                        $order->cancel();
                    }
                    break;

                default:
                    throw new Exception('Unhandled Qenta Checkout Seamless payment state:' . $return->getPaymentState());
            }

            $order->save();

            die(QentaCEE_QMore_ReturnFactory::generateConfirmResponseString());

        } catch (Exception $e) {
            $helper->log(__METHOD__ . ':' . $e->getMessage(), Zend_Log::ERR);

            die(QentaCEE_QMore_ReturnFactory::generateConfirmResponseString($e->getMessage()));
        }
    }

    /**
     * check if order already has been successfully processed.
     *
     * @param $order Mage_Sales_Model_Order
     *
     * @return bool
     */
    protected function _succeeded($order)
    {
        $history = $order->getAllStatusHistory();
        $paymentInst = $order->getPayment()->getMethodInstance();
        if ($paymentInst) {
            foreach ($history AS $entry) {
                if ($entry->getStatus() == Mage_Sales_Model_Order::STATE_PROCESSING) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Cancel an order
     *
     * @param Mage_Sales_Model_Order $order
     */
    protected function _cancelOrder($order)
    {
        /** @var Qenta_CheckoutSeamless_Helper_Data $helper */
        $helper = Mage::helper('qenta_checkoutseamless');

        if (!$this->_succeeded($order)) {
            $payment = $order->getPayment();
            $additionalInformation = Array('confirmProcessed' => true);
            $payment->setAdditionalInformation($additionalInformation);
            $payment->setAdditionalData(serialize($additionalInformation));
            $payment->save();

            if ($order->canUnhold()) {
                $order->unhold();
            }
            $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, $helper->__('Customer canceled the payment process'))->save();
            $order->cancel();
        }
    }

    /**
     * Confirm the payment of an order
     *
     * @param Mage_Sales_Model_Order $order
     * @param QentaCEE_Stdlib_Return_ReturnAbstract $return
     */
    protected function _confirmOrder($order, $return)
    {
        /** @var Qenta_CheckoutSeamless_Helper_Data $helper */
        $helper = Mage::helper('qenta_checkoutseamless');

        if (!$this->_succeeded($order)) {
            if ($return->getPaymentState() == QentaCEE_QMore_ReturnFactory::STATE_PENDING) {
                $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, $helper->__('The payment authorization is pending.'))->save();
            }
            else {
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, $helper->__('The amount has been authorized and captured by Qenta Checkout Seamless.'))->save();
                // invoice payment
                if ($order->canInvoice()) {

                    $invoice = $order->prepareInvoice();
                    $invoice->register()->capture();
                    Mage::getModel('core/resource_transaction')
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder())
                        ->save();
                }
                // send new order email to customer
                $order->sendNewOrderEmail();
            }
        }
        $payment = $order->getPayment();
        $additionalInformation = Array();

        foreach ($return->getReturned() as $fieldName => $fieldValue) {
            $additionalInformation[htmlentities($fieldName)] = htmlentities($fieldValue);
        }

        // need to remember whether confirm request was processed
        // check this within returnAction
        // could be if confirm request has bee blocked (firewall)
        $additionalInformation['confirmProcessed'] = true;

        $payment->setAdditionalInformation($additionalInformation);
        $payment->setAdditionalData(serialize($additionalInformation));
        $payment->save();
    }

}
