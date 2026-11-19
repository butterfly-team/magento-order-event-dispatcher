<?php
namespace Butterfly\OrderEventDispatcher\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Recent orders placed before this module tracked dispatches are treated as dispatched,
 * otherwise Butterfly's retry job would fire their events and emails again.
 */
class BackfillButterflyDispatchedAt implements DataPatchInterface
{
    const BATCH_SIZE = 1000;

    // Butterfly retries orders from the last 24 hours (--since); the rest is margin for a wider manual run.
    const WINDOW_DAYS = 30;

    private $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('sales_order');
        $range = $connection->fetchRow(
            $connection->select()
                ->from($table, ['min_id' => 'MIN(entity_id)', 'max_id' => 'MAX(entity_id)'])
                ->where('created_at >= ?', gmdate('Y-m-d H:i:s', time() - self::WINDOW_DAYS * 86400))
        );

        // Patches run in one transaction, so only a short window keeps the row locks small; older orders are never retried.
        for ($from = (int) $range['min_id']; $from && $from <= (int) $range['max_id']; $from += self::BATCH_SIZE) {
            $connection->update(
                $table,
                ['butterfly_dispatched_at' => new \Zend_Db_Expr('created_at')],
                [
                    'entity_id >= ?' => $from,
                    'entity_id < ?' => $from + self::BATCH_SIZE,
                    'butterfly_dispatched_at IS NULL',
                ]
            );
        }

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
