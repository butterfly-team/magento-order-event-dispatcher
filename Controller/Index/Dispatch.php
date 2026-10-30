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
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\ResourceConnection;


class Dispatch extends Action
{
    protected $eventManager;
    protected $orderRepository;
    protected $orderFactory;
    protected $resultJsonFactory;
    protected $scopeConfig;
    protected $orderSender;
    protected $stockManagement;
    protected $stockRegistryProvider;
    protected $subtractQuoteInventoryObserver;
    protected $storeManager;
    protected $resourceConnection;

    public function __construct(
        Context $context,
        ManagerInterface $eventManager,
        OrderRepositoryInterface $orderRepository,
        OrderFactory $orderFactory,
        JsonFactory $resultJsonFactory,
        ScopeConfigInterface $scopeConfig,
        OrderSender $orderSender,
        StockManagementInterface $stockManagement,
        StockRegistryProviderInterface $stockRegistryProvider,
        SubtractQuoteInventoryObserver $subtractQuoteInventoryObserver,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection
    ) {
        $this->eventManager = $eventManager;
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->orderSender = $orderSender;
        $this->stockManagement = $stockManagement;
        $this->stockRegistryProvider = $stockRegistryProvider;
        $this->subtractQuoteInventoryObserver = $subtractQuoteInventoryObserver;
        $this->storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;
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
            // Claim before loading the order, so an observer that saves it writes the claim back instead of NULL.
            if (!$this->claim($orderId)) {
                if (!$this->orderExists($orderId)) {
                    return $result->setData(['success' => false, 'message' => "Order not found."]);
                }

                return $result->setData([
                    'success' => true,
                    'already_dispatched' => true,
                    'message' => "Events were already dispatched for Order ID: " . $orderId
                ]);
            }

            $order = $this->orderRepository->get($orderId);

            if ($order->getId()) {
                try {
                    // Dispatch standard Magento events
                    $eventData = ['order' => $order, 'data_object' => $order, 'object' => $order];                    
                    
                    $this->eventManager->dispatch('sales_order_place_after', $eventData);
                    $this->eventManager->dispatch('checkout_submit_all_after', $eventData);
                    $this->eventManager->dispatch('sales_order_save_after', $eventData);

                    // Send order email
                    $this->orderSender->send($order);

                    return $result->setData([
                        'success' => true,
                        'message' => "Events dispatched and inventory updated for Order ID: " . $orderId
                    ]);

                } catch (\Exception $e) {
                    $this->release($orderId);

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

    /**
     * Only the request that flips butterfly_dispatched_at from NULL dispatches, so a
     * retry that races a slow first call cannot send the events or the email twice.
     */
    private function claim($orderId)
    {
        $connection = $this->resourceConnection->getConnection();

        return $connection->update(
            $this->resourceConnection->getTableName('sales_order'),
            ['butterfly_dispatched_at' => new \Zend_Db_Expr('NOW()')],
            ['entity_id = ?' => (int) $orderId, 'butterfly_dispatched_at IS NULL']
        ) > 0;
    }

    private function release($orderId)
    {
        $this->resourceConnection->getConnection()->update(
            $this->resourceConnection->getTableName('sales_order'),
            ['butterfly_dispatched_at' => null],
            ['entity_id = ?' => (int) $orderId]
        );
    }

    private function orderExists($orderId)
    {
        $connection = $this->resourceConnection->getConnection();

        return (bool) $connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('sales_order'), 'entity_id')
                ->where('entity_id = ?', (int) $orderId)
        );
    }
}
