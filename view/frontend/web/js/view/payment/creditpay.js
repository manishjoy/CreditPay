define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'creditpay',
                component: 'ManishJoy_CreditPay/js/view/payment/method-renderer/creditpay-method'
            }
        );
        return Component.extend({});
    }
);