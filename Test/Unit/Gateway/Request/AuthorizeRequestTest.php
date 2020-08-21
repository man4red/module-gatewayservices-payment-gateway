<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Manfred\GatewayServicesPaymentGateway\Test\Unit\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Manfred\GatewayServicesPaymentGateway\Gateway\Request\AuthorizationRequest;

class AuthorizeRequestTest extends \PHPUnit_Framework_TestCase
{
    public function testBuild()
    {
        $merchantApiPassword = 'api_password';
        $merchantPrivateKey = 'private_key';
        $invoiceId = 1001;
        $grandTotal = 12.2;
        $currencyCode = 'USD';
        $storeId = 1;
        $email = 'user@domain.com';

        $expectation = [
            'TXN_TYPE' => 'A',
            'INVOICE' => $invoiceId,
            'AMOUNT' => $grandTotal,
            'CURRENCY' => $currencyCode,
            'EMAIL' => $email,
            'MERCHANT_API_PASSWORD' => $merchantApiPassword,
            'MERCHANT_PRIVATE_KEY' => $merchantPrivateKey
        ];

        $configMock = $this->getMock(ConfigInterface::class);
        $orderMock = $this->getMock(OrderAdapterInterface::class);
        $addressMock = $this->getMock(AddressAdapterInterface::class);
        $payment = $this->getMock(PaymentDataObjectInterface::class);

        $payment->expects(static::any())
            ->method('getOrder')
            ->willReturn($orderMock);

        $orderMock->expects(static::any())
            ->method('getShippingAddress')
            ->willReturn($addressMock);

        $orderMock->expects(static::once())
            ->method('getOrderIncrementId')
            ->willReturn($invoiceId);
        $orderMock->expects(static::once())
            ->method('getGrandTotalAmount')
            ->willReturn($grandTotal);
        $orderMock->expects(static::once())
            ->method('getCurrencyCode')
            ->willReturn($currencyCode);
        $orderMock->expects(static::any())
            ->method('getStoreId')
            ->willReturn($storeId);

        $addressMock->expects(static::once())
            ->method('getEmail')
            ->willReturn($email);

        $configMock->expects(static::once())
            ->method('getValue')
            ->with('merchant_api_password', $storeId)
            ->willReturn($merchantApiPassword);

        $configMock->expects(static::once())
            ->method('getValue')
            ->with('merchant_private_key', $storeId)
            ->willReturn($merchantPrivateKey);

        /** @var ConfigInterface $configMock */
        $request = new AuthorizationRequest($configMock);

        static::assertEquals(
            $expectation,
            $request->build(['payment' => $payment])
        );
    }
}
