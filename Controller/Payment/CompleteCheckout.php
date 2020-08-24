<?php
namespace Manfred\GatewayServicesPaymentGateway\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\UrlInterface;
use \Magento\Directory\Model\CountryFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;

/**
 * Class GeneratePaymentForm
 *
 * @package Manfred\GatewayServicesPaymentGateway\Controller\Payment
 */
class CompleteCheckout extends Action implements CsrfAwareActionInterface
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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var OrderInformationManagementInterface
     */
    private $orderInformationManagement;

    /**
     * CompleteCheckout constructor.
     *
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param CheckoutSession $checkoutSession
     * @param Resolver $store
     * @param UrlInterface $urlBuilder
     * @param JsonFactory $resultJsonFactory
     * @param CountryFactory $countryFactory
     * @param Session $session
     * @param PageFactory $pageFactory
     * @param MessageManager $messageManager
     * @param OrderInformationManagementInterface $orderInformationManagement
     */

    public function __construct(
        Context $context,
        //\Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, 
        CheckoutSession $checkoutSession,
        Session $session,
        \Magento\Framework\Locale\Resolver $store,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        OrderStatusHistoryRepositoryInterface $orderStatusRepository,
        OrderRepositoryInterface $orderRepository
     ) {
        parent::__construct($context);
        //$this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig; //Used for getting data from System/Admin config
        $this->checkoutSession = $checkoutSession; //Used for getting the order: $order = $this->checkoutSession->getLastRealOrder(); And other order data like ID & amount
        $this->session = $session;
        $this->store = $store; //Used for getting store locale if needed $language_code = $this->store->getLocale();
        $this->urlBuilder = $urlBuilder; //Used for creating URLs to other custom controllers, for example $success_url = $this->urlBuilder->getUrl('frontname/path/action');
        $this->orderFactory = $orderFactory;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->orderRepository = $orderRepository;
    }

    /** 
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request 
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
    /*
     * @inheritdoc
     */
    public function execute()
    {
        $postData = $this->getRequest()->getPost();//get response
        $paramsData = $this->getRequest()->getParams();
        //$gws_trans = $paramsData['gws_trans'];

        // Take order
        try {
            $orderId = (!empty($this->checkoutSession->getLastRealOrderId())) ? $this->checkoutSession->getLastRealOrderId() : $paramsData['order_id'];
            $order = $this->orderRepository->get($orderId);
        } catch (Exception $e) {
            $error = "Oups! We couldn't find your order...";
            $this->messageManager->addErrorMessage(__($error));
            $this->exceptionLogger->error($e->getMessage());
            return $this->_redirect($this->urlBuilder->getUrl('checkout/cart'));
        }
 
        $result = false;
        try {
            //$result = simplexml_load_string(base64_decode($replyExampleMessage));
            $result = simplexml_load_string(base64_decode($postData['encodedMessage']));
            if (empty($result)) {
                $error = 'Unknown Payment Error, please try again';
                $this->messageManager->addErrorMessage(__($error));
            } elseif ((string)$result->PaymentStatus != 'APPROVED') {
                $error = 'Payment Error: ' . (string)$result->Description . ' (' . (string)$result->Code . ')';
                $this->messageManager->addErrorMessage(__($error));
            } elseif ((string)$result->PaymentStatus == 'APPROVED') {

                // State
                $orderState = \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW;
                // Message
                $message = 'Gateway Services Processing successful';
                $comment = $order->addStatusHistoryComment($message);
                $orderHistory = $this->orderStatusRepository->save($comment);

                $order->setState($orderState)->setStatus($orderState)->save();
                $this->orderRepository->save($order);

                $this->messageManager->addSuccessMessage(__($message));
                return $this->_redirect($this->urlBuilder->getUrl('checkout/onepage/success'));
            }
        } catch(Exception $e) {
            $this->messageManager->addErrorMessage(__($e->getMessage()));
            $this->exceptionLogger->error($e->getMessage());
        }
        return $this->_redirect($this->urlBuilder->getUrl('checkout/cart'));
    }
}
