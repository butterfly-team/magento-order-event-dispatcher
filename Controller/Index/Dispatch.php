<?php
namespace Butterfly\OrderEventDispatcher\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\CatalogInventory\Api\StockManagementInterface;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\CatalogInventory\Observer\SubtractQuoteInventoryObserver;
use Magento\Store\Model\StoreManagerInterface;

class Dispatch extends Action
{
    protected $eventManager;
    protected $orderFactory;
    protected $resultJsonFactory;
    protected $scopeConfig;
    protected $orderSender;
    protected $stockManagement;
    protected $stockRegistryProvider;
    protected $subtractQuoteInventoryObserver;
    protected $storeManager;

    public function __construct(
        Context $context,
        ManagerInterface $eventManager,
        OrderFactory $orderFactory,
        JsonFactory $resultJsonFactory,
        ScopeConfigInterface $scopeConfig,
        OrderSender $orderSender,
        StockManagementInterface $stockManagement,
        StockRegistryProviderInterface $stockRegistryProvider,
        SubtractQuoteInventoryObserver $subtractQuoteInventoryObserver,
        StoreManagerInterface $storeManager
    ) {
        $this->eventManager = $eventManager;
        $this->orderFactory = $orderFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->orderSender = $orderSender;
        $this->stockManagement = $stockManagement;
        $this->stockRegistryProvider = $stockRegistryProvider;
        $this->subtractQuoteInventoryObserver = $subtractQuoteInventoryObserver;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $orderId = $this->getRequest()->getParam('order_id');
        $providedSecret = $this->getRequest()->getParam('secret');

        // Retrieve the secret key from the Magento configuration
        $configSecret = $this->scopeConfig->getValue(
            'butterfly/order_event_dispatcher/secret_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        // Check if the provided secret matches the configured secret
        if (!$providedSecret || $providedSecret !== $configSecret) {
            return $result->setData(['success' => false, 'message' => 'Invalid secret key.']);
        }

        if ($orderId) {
            $order = $this->orderFactory->create()->load($orderId);
            
            if ($order->getId()) {
                try {
                    // Dispatch standard Magento events
                    $this->eventManager->dispatch('sales_order_place_after', ['order' => $order]);
                    $this->eventManager->dispatch('checkout_submit_all_after', ['order' => $order]);
                    $this->eventManager->dispatch('sales_order_save_after', ['order' => $order]);

                    // Send order email
                    $this->orderSender->send($order);

                    return $result->setData([
                        'success' => true,
                        'message' => "Events dispatched and inventory updated for Order ID: " . $orderId
                    ]);

                } catch (\Exception $e) {
                    return $result->setData([
                        'success' => false,
                        'message' => "Error processing order: " . $e->getMessage()
                    ]);
                }
            } else {
                return $result->setData(['success' => false, 'message' => "Order not found."]);
            }
        } else {
            return $result->setData(['success' => false, 'message' => "Order ID is required."]);
        }
    }
}
