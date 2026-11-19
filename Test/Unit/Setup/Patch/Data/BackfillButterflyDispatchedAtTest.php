<?php
namespace Butterfly\OrderEventDispatcher\Test\Unit\Setup\Patch\Data;

use Butterfly\OrderEventDispatcher\Setup\Patch\Data\BackfillButterflyDispatchedAt;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\TestCase;

class BackfillButterflyDispatchedAtTest extends TestCase
{
    public function testUpdatesRecentOrdersInIdBatchesOnlyWhereTheFlagIsEmpty()
    {
        $connection = new RecordingConnection(['min_id' => 1500, 'max_id' => 3700]);

        $this->apply($connection);

        $this->assertSame([[1500, 2500], [2500, 3500], [3500, 4500]], $connection->ranges());
        foreach ($connection->updates as $update) {
            $this->assertSame('sales_order', $update['table']);
            $this->assertSame('created_at', (string) $update['bind']['butterfly_dispatched_at']);
            $this->assertContains('butterfly_dispatched_at IS NULL', $update['where']);
        }
    }

    public function testRangeIsLimitedToTheRecentWindow()
    {
        $connection = new RecordingConnection(['min_id' => 1, 'max_id' => 1]);

        $this->apply($connection);

        $since = strtotime($connection->select->wheres['created_at >= ?'] . ' UTC');
        $expected = time() - BackfillButterflyDispatchedAt::WINDOW_DAYS * 86400;
        $this->assertEqualsWithDelta($expected, $since, 5);
    }

    public function testNoRecentOrdersMeansNoUpdates()
    {
        $connection = new RecordingConnection(['min_id' => null, 'max_id' => null]);

        $this->apply($connection);

        $this->assertSame([], $connection->updates);
    }

    private function apply(RecordingConnection $connection)
    {
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnArgument(0);

        (new BackfillButterflyDispatchedAt($setup))->apply();
    }
}

class RecordingConnection
{
    public $updates = [];
    public $select;
    private $range;

    public function __construct(array $range)
    {
        $this->range = $range;
    }

    public function select()
    {
        return $this->select = new RecordingSelect();
    }

    public function fetchRow($select)
    {
        return $this->range;
    }

    public function update($table, array $bind, $where)
    {
        $this->updates[] = ['table' => $table, 'bind' => $bind, 'where' => $where];

        return 0;
    }

    public function ranges()
    {
        return array_map(function ($update) {
            return [$update['where']['entity_id >= ?'], $update['where']['entity_id < ?']];
        }, $this->updates);
    }
}

class RecordingSelect
{
    public $wheres = [];

    public function from($table, $columns)
    {
        return $this;
    }

    public function where($condition, $value)
    {
        $this->wheres[$condition] = $value;

        return $this;
    }
}
