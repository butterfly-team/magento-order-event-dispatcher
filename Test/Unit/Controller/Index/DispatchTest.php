<?php
namespace Butterfly\OrderEventDispatcher\Test\Unit\Controller\Index;

use Butterfly\OrderEventDispatcher\Controller\Index\Dispatch;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use PHPUnit\Framework\TestCase;

class DispatchTest extends TestCase
{
    private const SECRET = 'secret';

    /** @var InMemorySalesOrder */
    private $salesOrder;

    /** @var string[] */
    private $events;

    /** @var int */
    private $emails;

    /** @var callable|null */
    private $observer;

    protected function setUp(): void
    {
        $this->salesOrder = new InMemorySalesOrder([100]);
        $this->events = [];
        $this->emails = 0;
        $this->observer = null;
    }

    public function testFirstCallDispatchesEventsSendsEmailAndClaimsTheOrder()
    {
        $response = $this->dispatch(100);

        $this->assertTrue($response['success']);
        $this->assertArrayNotHasKey('already_dispatched', $response);
        $this->assertSame(['sales_order_place_after', 'checkout_submit_all_after', 'sales_order_save_after'], $this->events);
        $this->assertSame(1, $this->emails);
        $this->assertNotNull($this->salesOrder->flag(100));
    }

    public function testRepeatedCallFiresNothingAndReportsAlreadyDispatched()
    {
        $this->dispatch(100);
        $response = $this->dispatch(100);

        $this->assertTrue($response['success']);
        $this->assertTrue($response['already_dispatched']);
        $this->assertCount(3, $this->events);
        $this->assertSame(1, $this->emails);
    }

    public function testErrorFromAnObserverReleasesTheClaimSoARetryDispatches()
    {
        $this->observer = function () {
            throw new \TypeError('observer blew up');
        };

        $failed = $this->dispatch(100);

        $this->assertFalse($failed['success']);
        $this->assertStringContainsString('observer blew up', $failed['message']);
        $this->assertNull($this->salesOrder->flag(100));
        $this->assertSame(0, $this->emails);

        $this->observer = null;
        $this->events = [];
        $retried = $this->dispatch(100);

        $this->assertTrue($retried['success']);
        $this->assertArrayNotHasKey('already_dispatched', $retried);
        $this->assertCount(3, $this->events);
        $this->assertSame(1, $this->emails);
    }

    public function testExceptionFromTheEmailSenderReleasesTheClaim()
    {
        $response = $this->dispatch(100, new \RuntimeException('smtp down'));

        $this->assertFalse($response['success']);
        $this->assertNull($this->salesOrder->flag(100));
    }

    public function testUnknownOrderIsReportedAsNotFound()
    {
        $response = $this->dispatch(999);

        $this->assertFalse($response['success']);
        $this->assertSame('Order not found.', $response['message']);
        $this->assertSame([], $this->events);
    }

    public function testWrongSecretTouchesNothing()
    {
        $response = $this->dispatch(100, null, 'wrong');

        $this->assertFalse($response['success']);
        $this->assertNull($this->salesOrder->flag(100));
        $this->assertSame([], $this->events);
    }

    private function dispatch($orderId, ?\Throwable $emailFailure = null, $secret = self::SECRET)
    {
        $controller = (new \ReflectionClass(Dispatch::class))->newInstanceWithoutConstructor();

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([
            ['order_id', null, $orderId],
            ['secret', null, $secret],
        ]);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(self::SECRET);

        $captured = new \ArrayObject();
        $json = $this->createMock(Json::class);
        $json->method('setData')->willReturnCallback(function ($data) use ($captured, $json) {
            $captured->exchangeArray($data);

            return $json;
        });
        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);

        $eventManager = $this->createMock(ManagerInterface::class);
        $eventManager->method('dispatch')->willReturnCallback(function ($name) {
            if ($this->observer) {
                ($this->observer)();
            }
            $this->events[] = $name;
        });

        $order = new class {
            public function getId()
            {
                return 100;
            }
        };
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('get')->willReturn($order);

        $orderSender = $this->createMock(OrderSender::class);
        $orderSender->method('send')->willReturnCallback(function () use ($emailFailure) {
            if ($emailFailure) {
                throw $emailFailure;
            }
            $this->emails++;
        });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->salesOrder);
        $resource->method('getTableName')->willReturnArgument(0);

        $properties = [
            '_request' => $request,
            'scopeConfig' => $scopeConfig,
            'resultJsonFactory' => $jsonFactory,
            'eventManager' => $eventManager,
            'orderRepository' => $orderRepository,
            'orderSender' => $orderSender,
            'resourceConnection' => $resource,
        ];
        foreach ($properties as $name => $value) {
            $property = new \ReflectionProperty($controller, $name);
            $property->setAccessible(true);
            $property->setValue($controller, $value);
        }

        $controller->execute();

        return $captured->getArrayCopy();
    }
}

/**
 * Stands in for the DB adapter: honours the `butterfly_dispatched_at IS NULL` guard the claim relies on.
 */
class InMemorySalesOrder
{
    /** @var array<int, string|null> */
    private $flags = [];

    public function __construct(array $orderIds)
    {
        foreach ($orderIds as $orderId) {
            $this->flags[$orderId] = null;
        }
    }

    public function flag($orderId)
    {
        return $this->flags[$orderId] ?? null;
    }

    public function update($table, array $bind, $where)
    {
        $orderId = $where['entity_id = ?'];
        if (!array_key_exists($orderId, $this->flags)) {
            return 0;
        }
        if (in_array('butterfly_dispatched_at IS NULL', $where, true) && $this->flags[$orderId] !== null) {
            return 0;
        }
        $value = $bind['butterfly_dispatched_at'];
        $this->flags[$orderId] = $value === null ? null : (string) $value;

        return 1;
    }

    public function select()
    {
        return new InMemorySelect();
    }

    public function fetchOne($select)
    {
        return array_key_exists($select->orderId, $this->flags) ? $select->orderId : false;
    }
}

class InMemorySelect
{
    public $orderId;

    public function from($table, $columns)
    {
        return $this;
    }

    public function where($condition, $value)
    {
        $this->orderId = $value;

        return $this;
    }
}
