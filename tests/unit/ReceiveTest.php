<?php
declare(strict_types=1);

final class ReceiveTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        fresh_db();
        make_item('A001');
    }

    public function testReceiveOutputUpdatesStatusAndDate(): void
    {
        $o = make_order('A001', 30);
        receive_process([$o['id'] => '出力'], '担当B');
        $row = order_find($o['id']);
        $this->assertSame('受付済', $row['ステータス']);
        $this->assertSame(today(), $row['受付日']);
        $this->assertSame('担当B', $row['受付者']);
    }

    public function testReceiveDeleteLogicallyRemovesUnreceivedOrder(): void
    {
        $o = make_order('A001', 30);
        $r = receive_process([$o['id'] => '削除'], '担当A');
        $this->assertSame(1, $r['削除']);
        $this->assertNull(order_find($o['id']));
    }

    public function testReceiveAlreadyReceivedIsSkipped(): void
    {
        $o = make_order('A001', 30);
        receive_process([$o['id'] => '出力'], '担当A');
        $r = receive_process([$o['id'] => '出力'], '担当B');
        $this->assertSame(0, $r['出力']);
        $row = order_find($o['id']);
        $this->assertSame('担当A', $row['受付者']); // 2回目はスキップ
    }

    public function testReceiveDeleteReceivedOrderIsSkipped(): void
    {
        $o = make_order('A001', 30);
        receive_process([$o['id'] => '出力'], '担当A');
        $r = receive_process([$o['id'] => '削除'], '担当A');
        $this->assertSame(0, $r['削除']);
        $this->assertNotNull(order_find($o['id']));
    }

    public function testReceiveListOnlyUnreceived(): void
    {
        $o1 = make_order('A001', 30);
        $o2 = make_order('A001', 20);
        receive_process([$o1['id'] => '出力'], '担当A');
        $ids = array_column(receive_list(), 'id');
        $this->assertSame([$o2['id']], $ids);
    }
}
