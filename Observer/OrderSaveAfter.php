<?php
namespace ManishJoy\CreditPay\Observer;

use Magento\Framework\Event\ObserverInterface;
use ManishJoy\CreditPay\Model\Config\Source\Order\Status\Credit as CreditStatus;

class OrderSaveAfter implements ObserverInterface
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    public function getOrderByIncrementId($incrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $incrementId)->create();
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();
        return count($orders)? $orders[key($orders)] : null;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try{
            $incrementId = $observer->getEvent()->getOrder()->getIncrementId();
            $loadedOrder = $this->getOrderByIncrementId($incrementId);
            $order = $this->orderRepository->get($loadedOrder->getId());
            $order->setStatus(CreditStatus::CREDIT_STATUS_CODE)->setState(CreditStatus::CREDIT_STATE_CODE);
            $this->orderRepository->save($order);           
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
        }
    }
}
