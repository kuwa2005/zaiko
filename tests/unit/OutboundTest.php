<?php
declare(strict_types=1);

final class OutboundTest extends PHPUnit\Framework\TestCase
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

    public function testRegisterSuccess(): void
    {
        $before = $this->stockOf('A001');
        $id = outbound_register(['コード' => 'A001', '出庫数' => 10, '備考' => '納品'], '担当A');
        $this->assertSame($before - 10, $this->stockOf('A001'));
        $s = outbound_find($id);
        $this->assertNotNull($s);
        $this->assertStringStartsWith('S', $s['管理NO']);
        $this->assertSame('10', (string)$s['出庫数']);
        $this->assertSame('担当A', $s['出庫者']);
        $this->assertSame(today(), $s['出庫日']);
    }

    public function testRegisterOverStockRejected(): void
    {
        $this->expectException(BizException::class);
        outbound_register(['コード' => 'A001', '出庫数' => 51], '担当A');
    }

    public function testRegisterExactStockAllowed(): void
    {
        outbound_register(['コード' => 'A001', '出庫数' => 50], '担当A');
        $this->assertSame(0.0, $this->stockOf('A001'));
    }

    public function testRegisterInvalidCodeRejected(): void
    {
        $this->expectException(BizException::class);
        outbound_register(['コード' => 'ZZZ', '出庫数' => 1], '担当A');
    }

    public function testRegisterZeroQuantityRejected(): void
    {
        $this->expectException(BizException::class);
        outbound_register(['コード' => 'A001', '出庫数' => 0], '担当A');
    }

    public function testRegisterNoPartialDataOnFailure(): void
    {
        try {
            outbound_register(['コード' => 'A001', '出庫数' => 999], '担当A');
        } catch (BizException $e) {
            // 期待どおり
        }
        $this->assertCount(0, outbound_list());
        $this->assertSame(50.0, $this->stockOf('A001'));
    }

    public function testUpdateAdjustsStock(): void
    {
        $id = outbound_register(['コード' => 'A001', '出庫数' => 10], '担当A');
        $before = $this->stockOf('A001');
        outbound_update($id, ['出庫数' => 4, '備考' => '変更'], '担当B');
        $this->assertSame($before + 6, $this->stockOf('A001'));
        $s = outbound_find($id);
        $this->assertSame('4', (string)$s['出庫数']);
        $this->assertSame('変更', $s['備考']);
    }

    public function testUpdateIncreaseOverStockRejected(): void
    {
        $id = outbound_register(['コード' => 'A001', '出庫数' => 10], '担当A');
        $this->expectException(BizException::class);
        outbound_update($id, ['出庫数' => 100], '担当A');
    }

    public function testDeleteRestoresStock(): void
    {
        $id = outbound_register(['コード' => 'A001', '出庫数' => 10], '担当A');
        $before = $this->stockOf('A001');
        outbound_delete($id, '担当A');
        $this->assertSame($before + 10, $this->stockOf('A001'));
        $this->assertNull(outbound_find($id));
    }

    public function testSequentialShipNumbers(): void
    {
        outbound_register(['コード' => 'A001', '出庫数' => 1], '担当A');
        outbound_register(['コード' => 'A001', '出庫数' => 1], '担当A');
        $nos = array_column(outbound_list(), '管理NO');
        $this->assertSame(['S0000000002', 'S0000000001'], $nos); // 降順
    }
}
