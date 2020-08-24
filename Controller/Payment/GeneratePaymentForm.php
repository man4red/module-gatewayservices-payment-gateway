<?php
namespace Manfred\GatewayServicesPaymentGateway\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\NotFoundException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\App\ObjectManager;

/**
 * Class GeneratePaymentForm
 *
 * @package Manfred\GatewayServicesPaymentGateway\Controller\Payment
 */
class GeneratePaymentForm extends Action
{

    /**
     * @var scopeConfig
     */
    private $scopeConfig;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @var ExceptionLogger
     */
    private $exceptionLogger;

    /**
     * @var OrderInformationManagementInterface
     */
    private $orderInformationManagement;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;


    public function __construct(
        Context $context,
        //\Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, 
        CartManagementInterface $cartManagement,
        GuestCartManagementInterface $guestCartManagement,
        CheckoutSession $checkoutSession,
        Session $session,
        \Magento\Framework\Locale\Resolver $store,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager = null,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     ) {
        parent::__construct($context);
        //$this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig; //Used for getting data from System/Admin config
        $this->cartManagement = $cartManagement;
        $this->checkoutSession = $checkoutSession; //Used for getting the order: $order = $this->checkoutSession->getLastRealOrder(); And other order data like ID & amount
        $this->session = $session;
        $this->store = $store; //Used for getting store locale if needed $language_code = $this->store->getLocale();
        $this->urlBuilder = $urlBuilder; //Used for creating URLs to other custom controllers, for example $success_url = $this->urlBuilder->getUrl('frontname/path/action');
        $this->resultJsonFactory = $resultJsonFactory; //Used for returning JSON data to the afterPlaceOrder function ($result = $this->resultJsonFactory->create(); return $result->setData($post_data);)
        $this->storeManager = $storeManager ?: ObjectManager::getInstance()
            ->get(\Magento\Store\Model\StoreManagerInterface::class);
        $this->orderRepository = $orderRepository;
    }

    /*
     * @inheritdoc
     */
    public function execute()
    {
        //ini_set('xdebug.var_display_max_depth', '3');
        //ini_set('xdebug.var_display_max_children', '250');
        //ini_set('xdebug.var_display_max_data', '1500');
        $params = $this->generateRequestData();
        $form_action_url = $params['form_action_url'];
        $post_data = array(
            'action' => $form_action_url,
            'fields' => array (
                'version' => $params['version'],
                'encodedMessage' => $params['encodedMessage'],
                'signature' => $params['signature'],
                //add all the fields you need
            )
        );
        $result = $this->resultJsonFactory->create();

        return $result->setData($post_data); //return data in JSON format
    }


    function generateRequestData()
    {
        $storeId = $this->storeManager->getStore()->getId();
        // Take order
        try {
            $order_id = $this->checkoutSession->getLastRealOrderId();
            if (empty($order_id)) {
                throw new \Exception("Oups! We couldn't find your order...");
            }
            $order = $this->orderRepository->get($order_id);
            // Set State/Status
            $orderState = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
            $order->setState($orderState)->setStatus($orderState)->save();
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__($e->getMessage()));
            $this->exceptionLogger->error($e->getMessage());
            return $this->_redirect('checkout/cart');
        }

        $merchant_id = $this->scopeConfig->getValue('payment/gatewayservices_gateway/merchant_id', 
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $terminal_id = $this->scopeConfig->getValue('payment/gatewayservices_gateway/terminal_id', 
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $merchant_api_password = $this->scopeConfig->getValue('payment/gatewayservices_gateway/merchant_api_password', 
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $merchant_private_key = $this->scopeConfig->getValue('payment/gatewayservices_gateway/merchant_private_key', 
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $transaction_type_id = $this->scopeConfig->getValue('payment/gatewayservices_gateway/transaction_type_id', 
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $test_mode = $this->scopeConfig->getValue('payment/gatewayservices_gateway/test_mode', 
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if($test_mode === '0') {
            $form_action_url = 'https://gateway-services.com/acquiring.php';
        } else {
            $form_action_url = 'https://test.gateway-services.com/acquiring.php';
        }

        if (!$this->session->isLoggedIn()) {
            $requestParams = $this->getRequest()->getParams();//get reequest params
            if(isset($requestParams['CustomerEmail'])) {
                $customer_email = $requestParams['CustomerEmail'];
            }
            $order->setCheckoutMethod(CartManagementInterface::METHOD_GUEST);
        } else {
            $customer_email = $order->getShippingAddress()->getCustomerEmail();
        }


        $return_url = $this->urlBuilder->getUrl('gatewayservices/payment/completecheckout');

        $TransactionId = intval("11" . rand(1, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9));
        $ApiPassword_encrypt = hash('sha256', $merchant_api_password);
        $xmlReq = '<?xml version="1.0" encoding="UTF-8" ?>
        <TransactionRequest>
            <Language>ENG</Language>
            <Credentials>
                <MerchantId>' . $merchant_id . '</MerchantId>
                <TerminalId>' . $terminal_id . '</TerminalId>
                <TerminalPassword>' . $ApiPassword_encrypt . '</TerminalPassword>
            </Credentials>
            <TransactionType>' . ($transaction_type_id != '' ? $transaction_type_id : 'LP101') . '</TransactionType>
            <TransactionId>' . $TransactionId . '</TransactionId>
            <ReturnUrl page="' . $return_url . '">
                <Param>
                    <Key>gws_trans</Key>
                    <Value>' . $TransactionId . '</Value>
                </Param>
                <Param>
                    <Key>order_id</Key>
                    <Value>' . $order_id . '</Value>
                </Param>
            </ReturnUrl>
            <CurrencyCode>' . $order->getBaseCurrency()->getCode() . '</CurrencyCode>
            <TotalAmount>' . number_format($order->getGrandTotal(), 2, '', '') . '</TotalAmount>
            <ProductDescription>store_id: ' . $storeId . '; order_id: ' . $order_id .'; tr_id: ' . $TransactionId . '</ProductDescription>
            <CustomerDetails>
                <FirstName>' . $order->getShippingAddress()->getFirstname() . '</FirstName>
                <LastName>' . $order->getShippingAddress()->getLastname() . '</LastName>
                <CustomerIP>' . $order->getData()['remote_ip'] . '</CustomerIP>
                <Phone>' . $order->getShippingAddress()->getTelephone() . '</Phone>
                <Email>' . $customer_email . '</Email>
                <Street>' .  $order->getShippingAddress()->getStreetLine(1) . '</Street>
                <City>' . $order->getShippingAddress()->getCity() . '</City>
                <Region>' . $order->getShippingAddress()->getRegion() . '</Region>
                <Country>' . $order->getBillingAddress()->getCountryId() . '</Country>
                <Zip>' . $order->getShippingAddress()->getPostcode() . '</Zip>
            </CustomerDetails>
        </TransactionRequest>';

        // Add signature
        $signature_key = trim($merchant_private_key . $merchant_api_password . $TransactionId);
        $signature = base64_encode(hash_hmac("sha256", trim($xmlReq), $signature_key, True));
        $encodedMessage = base64_encode($xmlReq);
        $params = array(
            'form_action_url' => $form_action_url,
            'version' => '1.0',
            'encodedMessage' => $encodedMessage,
            'signature' => $signature);
        return $params;
    }

    public function getOrder() {
        if ($this->checkoutSession->getLastRealOrderId()) {
            $order = $this->orderFactory->create()->loadByIncrementId($this->checkoutSession->getLastRealOrderId());
            return $order;
        }
        return false;
    }
}
