<?php

namespace Omniful\Core\Observer\Sales;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Omniful\Core\Model\Adapter;
use Omniful\Core\Logger\Logger;
use Omniful\Core\Model\Sales\Order as OrderManagement;
use Magento\Store\Model\StoreManagerInterface;

class OrderCancelAfter implements ObserverInterface
{
    public const EVENT_NAME = "order.canceled";
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var Adapter
     */
    protected $adapter;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var OrderManagement
     */
    protected $orderManagement;

    /**
     * Constructor Injection
     *
     * @param Logger $logger
     * @param Adapter $adapter
     * @param OrderManagement $orderManagement
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Logger $logger,
        Adapter $adapter,
        OrderManagement $orderManagement,
        StoreManagerInterface $storeManager
    ) {
        $this->logger = $logger;
        $this->adapter = $adapter;
        $this->storeManager = $storeManager;
        $this->orderManagement = $orderManagement;
    }

    /**
     * Triggers when an order is canceled and sending webhook message to omniful
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getOrder();
        $store = $order->getStore();
        $this->logger->info("Cancellation request received for Event: self::EVENT_NAME, Order: " . json_encode($order));

        try {
            if ($order->getStatus() == "canceled") {
                $eventName = self::EVENT_NAME;
                $storeData = $this->storeManager->getGroup($store->getGroupId());

                $headers = [
                    "website-code" => $order
                        ->getStore()
                        ->getWebsite()
                        ->getCode(),
                    "x-store-code" => $storeData->getCode(),
                    "x-store-view-code" => $order->getStore()->getCode(),
                ];

                // CONNECT FIRST
                $this->adapter->connect();
                // PUSH CANCEL ORDER EVENT
                $payload = $this->orderManagement->getOrderData($order);
                $response = $this->adapter->publishMessage(
                    $eventName,
                    $payload,
                    $headers
                );
                // LOG MESSAGE
                $this->logger->info(__("Order Canceled successfully"));
                return $response;
            }
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }
    }
}
