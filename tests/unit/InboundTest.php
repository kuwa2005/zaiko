<?php
declare(strict_types=1);

final class InboundTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        fresh_db();
        make_item('A001');
    }

    private function stockOf(string $code): float
    {
        return (float)db_val("SELECT 残数量 FROM 在庫マスタ WHERE コード = ?", [$code]);
    }

    public function testInboundAddsStockAndUpdatesOrder(): void
    {
        $o = make_order('A001', 30);
        $before = $this->stockOf('A001');
        $r = inbound_save([$o['id'] => '入庫'], '担当B');
        $this->assertSame(1, $r['入庫']);
        $this->assertSame($before + 30, $this->stockOf('A001'));
        $row = order_find($o['id']);
        $this->assertSame('入庫済', $row['ステータス']);
        $this->assertSame(today(), $row['入庫日']);
        $this->assertSame('担当B', $row['入庫者']);
    }

    public function testInboundCancelReversesStock(): void
    {
        $o = make_order('A001', 30);
        $before = $this->stockOf('A001');
        inbound_save([$o['id'] => '入庫'], '担当A');
        $r = inbound_save([$o['id'] => '入庫取消'], '担当A');
        $this->assertSame(1, $r['取消']);
        $this->assertSame($before, $this->stockOf('A001'));
        $row = order_find($o['id']);
        $this->assertSame('受付済', $row['ステータス']);
        $this->assertNull($row['入庫日']);
    }

    public function testInboundCancelNotInboundIsSkipped(): void
    {
        $o = make_order('A001', 30);
        $r = inbound_save([$o['id'] => '入庫取消'], '担当A');
        $this->assertSame(0, $r['取消']);
    }

    public function testInboundTwiceIsSkipped(): void
    {
        $o = make_order('A001', 30);
        inbound_save([$o['id'] => '入庫'], '担当A');
        $before = $this->stockOf('A001');
        $r = inbound_save([$o['id'] => '入庫'], '担当A');
        $this->assertSame(0, $r['入庫']);
        $this->assertSame($before, $this->stockOf('A001'));
    }

    public function testInboundDeleteRemovesStockIfInbound(): void
    {
        $o = make_order('A001', 30);
        inbound_save([$o['id'] => '入庫'], '担当A');
        $before = $this->stockOf('A001');
        $n = inbound_delete([$o['id'] => '削除'], '担当A');
        $this->assertSame(1, $n);
        $this->assertNull(order_find($o['id']));
        $this->assertSame($before - 30, $this->stockOf('A001'));
    }

    public function testInboundDeleteUnreceivedKeepsStock(): void
    {
        $o = make_order('A001', 30);
        $before = $this->stockOf('A001');
        $n = inbound_delete([$o['id'] => '削除'], '担当A');
        $this->assertSame(1, $n);
        $this->assertSame($before, $this->stockOf('A001'));
    }

    public function testInboundDeleteWithInboundMarkRejected(): void
    {
        $o = make_order('A001', 30);
        $this->expectException(BizException::class);
        inbound_delete([$o['id'] => '入庫'], '担当A');
    }

    public function testInboundSaveWithDeleteMarkRejected(): void
    {
        $o = make_order('A001', 30);
        $this->expectException(BizException::class);
        inbound_save([$o['id'] => '削除'], '担当A');
    }

    public function testInboundWithoutReceiveIsAllowed(): void
    {
        // 受付を経ずとも入庫可能
        $o = make_order('A001', 30);
        $r = inbound_save([$o['id'] => '入庫'], '担当A');
        $this->assertSame(1, $r['入庫']);
    }
}
