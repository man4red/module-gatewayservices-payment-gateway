define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/full-screen-loader',
        'Manfred_GatewayServicesPaymentGateway/js/form-builder'
    ],
    function ($, Component, url, customerData, errorProcessor, fullScreenLoader, formBuilder) {
        'use strict';
        return Component.extend({
            redirectAfterPlaceOrder: false, //This is important, so the customer isn't redirected to success.phtml by default
            defaults: {
                template: 'Manfred_GatewayServicesPaymentGateway/payment/form'
            },
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },

            afterPlaceOrder: function () {
                var custom_controller_url = url.build('gatewayservices/payment/generatepaymentform/?AuthenticationStatus=Success'); //your custom controller url
                $.post(custom_controller_url, 'json')
                .done(function (response) {
                    //customerData.invalidate(['cart']);
                    formBuilder(response).submit(); //this function builds and submits the form
                })
                .fail(function (response) {
                    errorProcessor.process(response, this.messageContainer);
                })
                .always(function () {
                    fullScreenLoader.stopLoader();
                });
            }

        });
    }
);