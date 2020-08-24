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
    function (
            $,
            Component,
            url,
            customerData,
            errorProcessor,
            fullScreenLoader,
            formBuilder
        ) {
        'use strict';
        return Component.extend({
            redirectAfterPlaceOrder: false, //This is important, so the customer isn't redirected to success.phtml by default
            defaults: {
                template: 'Manfred_GatewayServicesPaymentGateway/payment/form',
                transactionResult: '',
                customerEmail: '#customer-email'
            },

            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'transactionResult'
                    ]);
                return this;
            },

            getCode: function() {
                return 'gatewayservices_gateway';
            },

            getData: function() {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'transaction_result': this.transactionResult()
                    }
                };
            },

            getTransactionResults: function() {
                return _.map(window.checkoutConfig.payment.gatewayservices_gateway.transactionResults, function(value, key) {
                    return {
                        'value': key,
                        'transaction_result': value
                    }
                });
            },

            afterPlaceOrder: function () {
                var customerEmail = $(this.customerEmail).val();
                var custom_controller_url = url.build('gatewayservices/payment/generatepaymentform/?CustomerEmail=' + customerEmail);
                $.post(custom_controller_url, 'json')
                .done(function (response) {
                    customerData.invalidate(['cart']);
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