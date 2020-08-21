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

    public function __construct(
        Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, 
        Session $checkoutSession, 
        \Magento\Framework\Locale\Resolver $store,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory, 
        \Magento\Directory\Model\CountryFactory $countryFactory
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig; //Used for getting data from System/Admin config
        $this->checkoutSession = $checkoutSession; //Used for getting the order: $order = $this->checkoutSession->getLastRealOrder(); And other order data like ID & amount
        $this->store = $store; //Used for getting store locale if needed $language_code = $this->store->getLocale();
        $this->urlBuilder = $urlBuilder; //Used for creating URLs to other custom controllers, for example $success_url = $this->urlBuilder->getUrl('frontname/path/action');
        $this->resultJsonFactory = $resultJsonFactory; //Used for returning JSON data to the afterPlaceOrder function ($result = $this->resultJsonFactory->create(); return $result->setData($post_data);)
    }

    /*
     * @inheritdoc
     */
    public function execute()
    {
        //Your custom code for getting the data the payment provider needs
        //Structure your return data so the form-builder.js can build your form correctly
        $form_action_url = 'https://test.gateway-services.com/acquiring.php';
        $shop_id = '';
        $order_id = '';
        $api_key = '';
        $post_data = array(
            'action' => $form_action_url,
            'fields' => array (
                'shop_id' => $shop_id,
                'order_id' => $order_id,
                'api_key' => $api_key,
                //add all the fields you need
            )
        );
        $result = $this->resultJsonFactory->create();

        return $result->setData($post_data); //return data in JSON format
    }

}
