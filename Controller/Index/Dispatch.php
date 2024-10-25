<?php
namespace Butterfly\OrderEventDispatcher\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Dispatch extends Action
{
    protected $eventManager;
    protected $orderFactory;
    protected $resultJsonFactory;
    protected $scopeConfig;

    public function __construct(
        Context $context,
        ManagerInterface $eventManager,
        OrderFactory $orderFactory,
        JsonFactory $resultJsonFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->eventManager = $eventManager;
        $this->orderFactory = $orderFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $orderId = $this->getRequest()->getParam('order_id');
        $providedSecret = $this->getRequest()->getParam('secret');

        // Retrieve the secret key from the Magento configuration
        $configSecret = $this->scopeConfig->getValue('buterfly/order_event_dispatcher/secret_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        // Check if the provided secret matches the configured secret
        if (!$providedSecret || $providedSecret !== $configSecret) {
            return $result->setData(['success' => false, 'message' => 'Invalid secret key.']);
        }

        if ($orderId) {
            $order = $this->orderFactory->create()->load($orderId);

            if ($order->getId()) {
                // Dispatch events
                $this->eventManager->dispatch('sales_order_place_after', ['order' => $order]);
                $this->eventManager->dispatch('checkout_submit_all_after', ['order' => $order]);
                $this->eventManager->dispatch('sales_order_save_after', ['order' => $order]);

                return $result->setData(['success' => true, 'message' => "Events dispatched for Order ID: " . $orderId]);
            } else {
                return $result->setData(['success' => false, 'message' => "Order not found."]);
            }
        } else {
            return $result->setData(['success' => false, 'message' => "Order ID is required."]);
        }
    }
}
