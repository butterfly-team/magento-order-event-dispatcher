<?php
namespace Butterfly\OrderEventDispatcher\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Orders placed before this module tracked dispatches are treated as dispatched,
 * otherwise Butterfly's retry job would fire their events and emails again.
 */
class BackfillButterflyDispatchedAt implements DataPatchInterface
{
    private $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->update(
            $this->moduleDataSetup->getTable('sales_order'),
            ['butterfly_dispatched_at' => new \Zend_Db_Expr('created_at')],
            'butterfly_dispatched_at IS NULL'
        );

        return $this;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
