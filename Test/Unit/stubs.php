<?php
// Minimal stand-ins so the unit tests run without a Magento install; inside Magento the real classes win.
namespace Magento\Framework\App\Action {
    if (!class_exists(Action::class)) {
        abstract class Action
        {
            protected $_request;

            public function getRequest()
            {
                return $this->_request;
            }
        }
    }
    if (!class_exists(Context::class)) {
        class Context {}
    }
}

namespace Magento\Framework\App\Config {
    if (!interface_exists(ScopeConfigInterface::class)) {
        interface ScopeConfigInterface
        {
            public function getValue($path, $scopeType = 'default', $scopeCode = null);
        }
    }
}

namespace Magento\Framework\App {
    if (!interface_exists(RequestInterface::class)) {
        interface RequestInterface
        {
            public function getParam($key, $defaultValue = null);
        }
    }
    if (!class_exists(ResourceConnection::class)) {
        class ResourceConnection
        {
            public function getConnection($resourceName = 'default') {}
            public function getTableName($modelEntity, $connectionName = 'default') {}
        }
    }
}

namespace Magento\Framework\Event {
    if (!interface_exists(ManagerInterface::class)) {
        interface ManagerInterface
        {
            public function dispatch($eventName, array $data = []);
        }
    }
}

namespace Magento\Framework\Controller\Result {
    if (!class_exists(Json::class)) {
        class Json
        {
            public function setData($data, $cycleCheck = false, $cycleRefs = []) {}
        }
    }
    if (!class_exists(JsonFactory::class)) {
        class JsonFactory
        {
            public function create(array $data = []) {}
        }
    }
}

namespace Magento\Sales\Api {
    if (!interface_exists(OrderRepositoryInterface::class)) {
        interface OrderRepositoryInterface
        {
            public function get($id);
        }
    }
}

namespace Magento\Sales\Model\Order\Email\Sender {
    if (!class_exists(OrderSender::class)) {
        class OrderSender
        {
            public function send($order, $forceSyncMode = false) {}
        }
    }
}

namespace Magento\Store\Model {
    if (!class_exists(ScopeInterface::class)) {
        interface ScopeInterface
        {
            const SCOPE_STORE = 'store';
        }
    }
}

namespace {
    if (!class_exists(Zend_Db_Expr::class)) {
        class Zend_Db_Expr
        {
            private $expression;

            public function __construct($expression)
            {
                $this->expression = $expression;
            }

            public function __toString()
            {
                return (string) $this->expression;
            }
        }
    }
}

namespace Magento\Framework\Component {
    if (!class_exists(ComponentRegistrar::class)) {
        class ComponentRegistrar
        {
            const MODULE = 'module';

            public static function register($type, $componentName, $path) {}
        }
    }
}
