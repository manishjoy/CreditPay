<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ManishJoy\CreditPay\Model\Payment;

use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

class CreditPay extends \Magento\Payment\Model\Method\AbstractMethod
{

    protected $_code                        = "creditpay";
    protected $_isOffline                   = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;

    const CUSTOMER_CREDIT_ATTRIBUTE = 'customer_credit';
    const ACTION_AUTHORIZE = 'authorize';
    const ACTION_AUTHORIZE_CAPTURE = 'authorize_capture';

    /**
     * @var DirectoryHelper
     */
    private $directory;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var PricingHelper
     */
    private $pricingHelper;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @param DirectoryHelper $directory
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param PricingHelper $pricingHelper
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        PricingHelper $pricingHelper
    ) {
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->pricingHelper = $pricingHelper;
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Payment capturing
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        try {
            $order = $payment->getOrder();
            $customerId = $this->getCustomerId() ? $this->getCustomerId() : $order->getCustomerId();
            $customerCreditValue = $this->getCustomerCreditValue($customerId);
            $this->setCustomerCreditValue($customerId, $customerCreditValue - $amount);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('CreditPay Payment Exception: %1', $e->getMessage()));
        }
        return $this;
    }

    /**
     * Refund specified amount for payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @deprecated 100.2.0
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount) {
        try {
            $order = $payment->getOrder();
            $customerId = $this->getCustomerId() ? $this->getCustomerId() : $order->getCustomerId();
            $customerCreditValue = $this->getCustomerCreditValue($customerId);
            $this->setCustomerCreditValue($customerId, $customerCreditValue + $amount);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('CreditPay Refund Exception: %1', $e->getMessage()));
        }
        return $this;
    }
    
    /**
     * Get config payment action url.
     *
     * Used to universalize payment actions when processing payment place.
     *
     * @return string
     * @api
     * @deprecated 100.2.0
     */
    public function getConfigPaymentAction()
    {
        return self::ACTION_AUTHORIZE_CAPTURE;
    }

    /**
     * Retrieve payment method title
     *
     * @return string
     * @deprecated 100.2.0
     */
    public function getTitle()
    {
        return __('%1 (Available Balance: %2)', $this->getConfigData('title'), $this->pricingHelper->currency($this->getCustomerCreditValue($this->getCustomerId()), true, false));
    }

    /**
     * Check whether payment method can be used
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     * @deprecated 100.2.0
     */
    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        $customerId = $this->getCustomerId() ? $this->getCustomerId() : $quote->getCustomer()->getId();
        /* Check if customer is logged in and has enough credit balance to place the order with the payment method */
        if (
            $this->isCustomerLoggedIn() 
            && $quote->getGrandTotal() <= $this->getCustomerCreditValue($customerId)
        ) {
            return parent::isAvailable($quote);
        }
        
    }

    /* Customer Data Processing */

    public function isCustomerLoggedIn()
    {
        return $this->customerSession->isLoggedIn();
    }

    public function getCustomerId()
    {
        return $this->customerSession->getCustomer()->getId();
    }

    public function getCustomerCreditValue($customerId)
    {
        $customer = $this->customerRepository->getById($customerId);
        $customerCreditValue = $customer->getCustomAttribute(self::CUSTOMER_CREDIT_ATTRIBUTE) ? $customer->getCustomAttribute(self::CUSTOMER_CREDIT_ATTRIBUTE)->getValue() : 0;
        return floatval($customerCreditValue);
    }

    public function setCustomerCreditValue($customerId, $creditAmount)
    {
        $customer = $this->customerRepository->getById($customerId);
        $customer->setCustomAttribute(self::CUSTOMER_CREDIT_ATTRIBUTE, $creditAmount);
        return $this->customerRepository->save($customer);
    }
}
