## Synopsis
An extension to add integration with Gateway Services Payment Gateway.

## Technical feature

### Module configuration
1. Package details [composer.json](composer.json).
2. Module configuration details (sequence) in [module.xml](etc/module.xml).
3. Module configuration available through Stores->Configuration [system.xml](etc/adminhtml/system.xml)

Payment gateway module depends on `Sales`, `Payment` and `Checkout` Magento modules.
For more module configuration details, please look through [module development docs](http://devdocs.magento.com/guides/v2.0/extension-dev-guide/module-load-order.html).

### Gateway configuration
On the next step, we might specify gateway domain configuration in [config.xml](etc/config.xml).

##### Let's look into configuration attributes:
 * <code>debug</code> enables debug mode by default, e.g log for request/response
 * <code>active</code> is payment active by default
 * <code>model</code> `Payment Method Facade` used for integration with `Sales` and `Checkout` modules
 * <code>merchant_gateway_key</code> encrypted merchant credential
 * <code>order_status</code> default order status
 * <code>payment_action</code> default action of payment
 * <code>title</code> default title for a payment method
 * <code>currency</code> supported currency
 * <code>can_authorize</code> whether payment method supports authorization
 * <code>can_capture</code> whether payment method supports capture
 * <code>can_void</code> whether payment method supports void
 * <code>can_use_checkout</code> checkout availability
 * <code>is_gateway</code> is an integration with gateway
 * <code>sort_order</code> payment method order position on checkout/system configuration pages
 * <code>debugReplaceKeys</code> request/response fields, which will be masked in log
 * <code>paymentInfoKeys</code> transaction request/response fields displayed on payment information block
 * <code>privateInfoKeys</code> paymentInfoKeys fields which should not be displayed in customer payment information block

### Dependency Injection configuration
> To get more details about dependency injection configuration in Magento 2, please see [DI docs](http://devdocs.magento.com/guides/v2.0/extension-dev-guide/depend-inj.html).

In a case of Payment Gateway, DI configuration is used to define pools of `Gateway Commands` with related infrastructure and to configure `Payment Method Facade` (used by `Sales` and `Checkout` modules to perform commands)

Payment Method Facade configuration:
```xml
<!-- Payment Method Facade configuration -->
<virtualType name="GatewayServicesPaymentGatewayFacade" type="Magento\Payment\Model\Method\Adapter">
    <arguments>
        <argument name="code" xsi:type="const">\Manfred\GatewayServicesPaymentGateway\Model\Ui\ConfigProvider::CODE</argument>
        <argument name="formBlockType" xsi:type="string">Magento\Payment\Block\Form</argument>
        <argument name="infoBlockType" xsi:type="string">Manfred\GatewayServicesPaymentGateway\Block\Info</argument>
        <argument name="valueHandlerPool" xsi:type="object">GatewayServicesPaymentGatewayValueHandlerPool</argument>
        <argument name="commandPool" xsi:type="object">GatewayServicesPaymentGatewayCommandPool</argument>
    </arguments>
</virtualType>
```
 * <code>code</code> Payment Method code
 * <code>formBlockType</code> Block class name, responsible for Payment Gateway Form rendering on a checkout.
  Since Opepage Checkout uses knockout.js for rendering, this renderer is used only during Checkout process from Admin panel.
 * <code>infoBlockType</code> Block class name, responsible for Transaction/Payment Information details rendering in Order block.
 * <code>valueHandlerPool</code> Value handler pool used for queries to configuration
 * <code>commandPool</code> Pool of Payment Gateway commands


#### Pools
> ! All `Payment\Gateway` provided pools implementations use lazy loading for components, i.e configured with component type name instead of component object

##### Value Handlers
There should be at least one Value Handler with `default` key provided for ValueHandlerPool.

```xml
!-- Value handlers infrastructure -->
<virtualType name="GatewayServicesPaymentGatewayValueHandlerPool" type="Magento\Payment\Gateway\Config\ValueHandlerPool">
    <arguments>
        <argument name="handlers" xsi:type="array">
            <item name="default" xsi:type="string">GatewayServicesPaymentGatewayConfigValueHandler</item>
        </argument>
    </arguments>
</virtualType>
<virtualType name="GatewayServicesPaymentGatewayConfigValueHandler" type="Magento\Payment\Gateway\Config\ConfigValueHandler">
    <arguments>
        <argument name="configInterface" xsi:type="object">GatewayServicesPaymentGatewayConfig</argument>
    </arguments>
</virtualType>
```

##### Commands
All gateway commands should be added to CommandPool instance
```xml
<!-- Commands infrastructure -->
<virtualType name="GatewayServicesPaymentGatewayCommandPool" type="Magento\Payment\Gateway\Command\CommandPool">
    <arguments>
        <argument name="commands" xsi:type="array">
            <item name="authorize" xsi:type="string">GatewayServicesPaymentGatewayAuthorizeCommand</item>
            <item name="capture" xsi:type="string">GatewayServicesPaymentGatewayCaptureCommand</item>
            <item name="void" xsi:type="string">GatewayServicesPaymentGatewayVoidCommand</item>
        </argument>
    </arguments>
</virtualType>
```

Example of Authorization command configuration:
```xml
<!-- Authorize command -->
<virtualType name="GatewayServicesPaymentGatewayAuthorizeCommand" type="Magento\Payment\Gateway\Command\GatewayCommand">
    <arguments>
        <argument name="requestBuilder" xsi:type="object">GatewayServicesPaymentGatewayAuthorizationRequest</argument>
        <argument name="handler" xsi:type="object">GatewayServicesPaymentGatewayResponseHandlerComposite</argument>
        <argument name="transferFactory" xsi:type="object">Manfred\GatewayServicesPaymentGateway\Gateway\Http\TransferFactory</argument>
        <argument name="client" xsi:type="object">Manfred\GatewayServicesPaymentGateway\Gateway\Http\Client\ClientMock</argument>
    </arguments>
</virtualType>

<!-- Authorization Request -->
<virtualType name="GatewayServicesPaymentGatewayAuthorizationRequest" type="Magento\Payment\Gateway\Request\BuilderComposite">
    <arguments>
        <argument name="builders" xsi:type="array">
            <item name="transaction" xsi:type="string">Manfred\GatewayServicesPaymentGateway\Gateway\Request\AuthorizationRequest</item>
            <item name="mockData" xsi:type="string">Manfred\GatewayServicesPaymentGateway\Gateway\Request\MockDataRequest</item>
        </argument>
    </arguments>
</virtualType>
<type name="Manfred\GatewayServicesPaymentGateway\Gateway\Request\AuthorizationRequest">
    <arguments>
        <argument name="config" xsi:type="object">GatewayServicesPaymentGatewayConfig</argument>
    </arguments>
</type>

<!-- Response handlers -->
<virtualType name="GatewayServicesPaymentGatewayResponseHandlerComposite" type="Magento\Payment\Gateway\Response\HandlerChain">
    <arguments>
        <argument name="handlers" xsi:type="array">
            <item name="txnid" xsi:type="string">Manfred\GatewayServicesPaymentGateway\Gateway\Response\TxnIdHandler</item>
            <item name="fraud" xsi:type="string">Manfred\GatewayServicesPaymentGateway\Gateway\Response\FraudHandler</item>
        </argument>
    </arguments>
</virtualType>
```
* `GatewayServicesPaymentGatewayAuthorizeCommand` - instance of GatewayCommand provided by `Payment\Gateway` configured with request builders, response handlers and transfer client
* `GatewayServicesPaymentGatewayAuthorizationRequest` - Composite of request parts used for Authorization
* `GatewayServicesPaymentGatewayResponseHandlerComposite` - Composite\List of response handlers.

## Installation
This module is intended to be installed using composer.  After including this component and enabling it, you can verify it is installed by going the backend at:
STORES -> Configuration -> ADVANCED/Advanced ->  Disable Modules Output
Once there check that the module name shows up in the list to confirm that it was installed correctly.

## Tests
Unit tests could be found in the [Test/Unit](Test/Unit) directory.

## Contributors
Magento Core team

## License
[Open Source License](LICENSE.txt)
