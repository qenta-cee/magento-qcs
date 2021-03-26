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

 var qentaCheckoutSeamlessResponseData = [];

 function toggleQentaCheckoutSeamlessIFrame() {
     if ($('qenta-checkoutseamless-iframe-div')) {
         var viewportHeight = document.viewport.getHeight(),
             docHeight = $$('body')[0].getHeight(),
             height = docHeight > viewportHeight ? docHeight : viewportHeight;
         $('qenta-checkoutseamless-iframe-div').toggle();
         $('window-overlay-qenta-checkoutseamless').setStyle({height: height + 'px'}).toggle();
     }
 }
 
 Event.observe(window, 'load', function () {
     if (typeof WirecardCEE_DataStorage == 'function') {
         payment.save = payment.save.wrap(function (origSaveMethod) {
             if (this.currentMethod
                 && this.currentMethod.substr(0, 22) == 'qenta_checkoutseamless'
                 && $(this.currentMethod + '_new') !== null ) {
 
                 var paymentData = null;
                 $$("#payment_form_" + this.currentMethod + ' .has-wcs-data').each(function (item) {
                     if (item.hasAttribute('data-wcs-fieldname')) {
                         var fieldname = item.readAttribute('data-wcs-fieldname');
                         if (typeof fieldname == 'undefined')
                             return true;
 
                         if (paymentData === null) {
                             paymentData = {};
                         }
                         paymentData[fieldname] = item.value;
                     }
                 });
 
                 if (paymentData === null && typeof $('#' + this.currentMethod + '_pci3') !== 'undefined')
                     paymentData = { };
 
                 if (paymentData !== null && $(this.currentMethod + '_type') !== null){
                     paymentData.paymentType = $(this.currentMethod + '_type').value;
                 }
 
                 if (paymentData === null
                     || (typeof qentaCheckoutSeamlessResponseData[this.currentMethod] !== 'undefined'
                     && qentaCheckoutSeamlessResponseData[this.currentMethod] !== false)) {
                     origSaveMethod();
                     return;
                 }
 
                 if (checkout.loadWaiting != false) return;
 
                 var validator = new Validation(this.form);
                 var valid = validator.validate();
                 if (this.validate() && valid) {
                     checkout.setLoadWaiting('payment');
                     var application = new qentaCheckoutSeamlessApplication();
                     application.sendRequest(paymentData);
                 }
 
             } else {
                 origSaveMethod();
             }
         });
     }
 
     Review.prototype.nextStep = Review.prototype.nextStep.wrap(function (next, transport) {
         outerTransport = transport;
         nextStep = next;
         var outerResponse = eval('(' + outerTransport.responseText + ')');
         if (typeof outerResponse.redirect == 'undefined') {
             nextStep(outerTransport);
         }
         else {
             var params = {'paymentMethod': payment.currentMethod};
 
             var request = new Ajax.Request(
                 qentaCheckoutSeamlessRedirectUrl,
                 {
                     method: 'get',
                     parameters: params,
                     onSuccess: function (innerTransport) {
                         if (innerTransport && innerTransport.responseText) {
                             try {
                                 var innerResponse = eval('(' + innerTransport.responseText + ')');
                                 var outerResponse = eval('(' + outerTransport.responseText + ')');
                             }
                             catch (e) {
                                 innerResponse = {};
                             }
                             if (innerResponse.url) {
                                 //show iframe and set link
                                 //toggleQentaCheckoutSeamlessIFrame();
                                 //$('qenta-checkoutseamless-iframe').src = innerResponse.url;
                                 if(payment.currentMethod.substr(23) == 'sofortbanking') {
                                     window.location.href = innerResponse.url;
                                 }
                                 else {
                                     var width = 500;
                                     if (document.body.clientWidth < 500) {
                                         width = document.body.clientWidth;
                                     }
                                     var oPopup = new Window({
                                         id:'popup_window',
                                         className: 'magento',
                                         windowClassName: 'magentopopup',
                                         url: innerResponse.url,
                                         width: width,
                                         height: 500,
                                         minimizable: false,
                                         maximizable: false,
                                         closable: false,
                                         showEffectOptions: {
                                             duration: 0.4
                                         },
                                         hideEffectOptions:{
                                             duration: 0.4
                                         },
                                         destroyOnClose: true
                                     });
                                     oPopup.setZIndex(100);
                                     oPopup.showCenter(true);
                                     $('#popup_window_content').width(width);
                                 }
                             }
                             else {
                                 nextStep(outerTransport);
                             }
                         }
                     },
                     onFailure: function (innerTransport) {
                         nextStep(outerTransport);
                     }
                 });
         }
     });
 
 });
 
 var qentaCheckoutSeamlessApplication = Class.create();
 qentaCheckoutSeamlessApplication.prototype = {
 
     initialize: function () {
     },
 
     sendRequest: function (data) {
 
         var qentaCee = new WirecardCEE_DataStorage;
         var request = qentaCee.storePaymentInformation(data, function (response) {
 
             processResponse(response);
 
             if (response.getStatus() == 0) {
 
                 new Ajax.Request(
                     qentaCheckoutSeamlessSaveSessInfo,
                     {
                         method: 'post',
                         parameters: Form.serialize(payment.form)
                     }
                 );
 
                 var request = new Ajax.Request(
                     payment.saveUrl,
                     {
                         method: 'post',
                         onComplete: payment.onComplete,
                         onSuccess: payment.onSave,
                         onFailure: checkout.ajaxFailure.bind(checkout),
                         parameters: Form.serialize(payment.form)
                     }
                 );
             }
         });
 
         // no postMessage support, make read request to datastore to check for stored data
         if (request === null) {

             new Ajax.Request(
                 qentaCheckoutSeamlessReadDatastorage,
                 {
                     method: 'post',
                     parameters: Form.serialize(payment.form),
                     onComplete: function (resp) {
                         var response = window.JSON.parse(resp.responseText);
 
                         if (response.status == 1) {
                             new Ajax.Request(
                                 payment.saveUrl,
                                 {
                                     method: 'post',
                                     onComplete: payment.onComplete,
                                     onSuccess: payment.onSave,
                                     onFailure: checkout.ajaxFailure.bind(checkout),
                                     parameters: Form.serialize(payment.form)
                                 }
                             );
                         } else {
                             alert('no stored paymentinformation found');
                             checkout.setLoadWaiting(false);
                         }
                     }
                 });
         }
     }
 }
 
 function processResponse(response) {
     if (response.getErrors()) {
         var errorMsg = '';
         var errors = response.response.error;
         for (var i = 0; i <= response.response.errors; i++) {
             if (typeof errors[i] === 'undefined') {
                 continue;
             }
             errorMsg += errors[i].consumerMessage.strip() + "\n\r";
         }
         //we have to htmlentities decode this
         alert(html_entity_decode(errorMsg));
         checkout.setLoadWaiting(false);
 
     } else {
 
         if (payment.currentMethod.substr(23) == 'cc' || payment.currentMethod.substr(23) == 'ccMoto') {
 
             // pci3 mode
             if ($(payment.currentMethod + '_pan') === null)
                 return;
         }
 
         prepareSubmittedFields(response.response);
         qentaCheckoutSeamlessResponseData[payment.currentMethod] = true;
     }
 }
 
 function html_entity_decode(str) {
     //jd-tech.net
     var tarea = document.createElement('textarea');
     tarea.innerHTML = str;
     return tarea.value;
 }
 
 function prepareSubmittedFields(response) {
     if (payment.currentMethod.substr(23) == 'cc' || payment.currentMethod.substr(23) == 'ccMoto') {
         enterAnonData(response.paymentInformation);
         $(payment.currentMethod + '_saved_data').show();
         $(payment.currentMethod + '_new_data').hide();
         emptyPaymentFields();
     } else {
 
         $$('#payment_form_' + payment.currentMethod + ' .no-submit').each(function (el) {
             el.observe('change', function (el) {
                 $(payment.currentMethod + '_new').value = '1';
                 qentaCheckoutSeamlessResponseData[payment.currentMethod] = false;
             });
         });
     }
 
     var elements = $('payment_form_' + payment.currentMethod).select('input[type="hidden"]').each(function (el) {
         switch (el.name) {
             case 'payment[cc_owner]':
             case 'payment[ccMoto_owner]':
                 el.value = response.paymentInformation.cardholdername;
                 break;
             case 'payment[cc_type]':
             case 'payment[ccMoto_type]':
                 el.value = response.paymentInformation.brand;
                 break;
             case 'payment[cc_number]':
             case 'payment[ccMoto_number]':
                 el.value = response.paymentInformation.anonymousPan;
                 break;
             case 'payment[cc_exp_month]':
             case 'payment[ccMoto_exp_month]':
                 el.value = response.paymentInformation.expiry.substr(0, response.paymentInformation.expiry.lastIndexOf('/'));
                 break;
             case 'payment[cc_exp_year]':
             case 'payment[ccMoto_exp_year]':
                 el.value = response.paymentInformation.expiry.substr(response.paymentInformation.expiry.lastIndexOf('/') + 1);
                 break;
             case payment.currentMethod + '_new':
                 el.value = '0';
                 break;
             default:
                 break;
         }
     });
 
     if (payment.currentMethod.substr(23) == 'sepadd' || payment.currentMethod.substr(23) == 'paybox'
         || payment.currentMethod.substr(23) == 'voucher' || payment.currentMethod.substr(23) == 'giropay') {
         emptyPaymentFields();
     }
 }
 
 function changePaymentData() {
 
     new Ajax.Request(
         qentaCheckoutSeamlessDeleteSessInfo,
         {
             method: 'post',
             onSuccess: function () {
                 emptyHiddenFields();
                 qentaCheckoutSeamlessResponseData[payment.currentMethod] = false;
                 $(payment.currentMethod + '_new').value = '1';
                 $(payment.currentMethod + '_saved_data').hide();
                 $(payment.currentMethod + '_new_data').show();
             }
         }
     );
 }
 
 function emptyPaymentFields() {
     $$('#payment_form_' + payment.currentMethod + ' .no-submit').each(function (el) {
         this.value = '';
     });
 }
 
 function emptyHiddenFields() {
     $$('#payment_form_' + payment.currentMethod + ' .wcs-anon-data-hidden').each(function (el) {
         this.value = '';
     });
 }
 
 function enterAnonData(data) {
     $$('#' + payment.currentMethod + '_saved_data span').each(function (el) {
         this.innerHTML = data[this.id];
     });
 }