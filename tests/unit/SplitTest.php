<?php
declare(strict_types=1);

final class SplitTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        fresh_db();
        make_item('A001');
        make_item('A002', ['残数量' => 5]);
    }

    public function testSplitSuccess(): void
    {
        $o = make_order('A001', 20);
        $n = order_split($o['id'], ['数量1' => 12, '納期1' => future_date(5), '数量2' => 8, '納期2' => future_date(10)], '担当A');
        $this->assertSame(2, $n);

        // 親はクローズ（分割済・削除フラグ）
        $parent = db_one("SELECT * FROM 発注データ WHERE id = ?", [$o['id']]);
        $this->assertSame('分割済', $parent['ステータス']);
        $this->assertSame(1, (int)$parent['削除フラグ']);

        // 子が注番=親管理NOで生成
        $kids = db_all("SELECT * FROM 発注データ WHERE 注番 = ? AND 削除フラグ = 0 ORDER BY 管理NO", [$o['管理NO']]);
        $this->assertCount(2, $kids);
        $this->assertSame($o['管理NO'] . '-1', $kids[0]['管理NO']);
        $this->assertSame($o['管理NO'] . '-2', $kids[1]['管理NO']);
        $this->assertSame('未受付', $kids[0]['ステータス']);
        $this->assertSame('12', (string)$kids[0]['数量']);
        $this->assertSame('8', (string)$kids[1]['数量']);
    }

    public function testSplitThreeWay(): void
    {
        $o = make_order('A001', 30);
        $n = order_split($o['id'], ['数量1' => 10, '納期1' => future_date(1), '数量2' => 10, '納期2' => future_date(2), '数量3' => 10, '納期3' => future_date(3)], '担当A');
        $this->assertSame(3, $n);
    }

    public function testSplitQuantityMismatchRejected(): void
    {
        $o = make_order('A001', 20);
        $this->expectException(BizException::class);
        order_split($o['id'], ['数量1' => 5, '納期1' => future_date()], '担当A');
    }

    public function testSplitEmptyRejected(): void
    {
        $o = make_order('A001', 20);
        $this->expectException(BizException::class);
        order_split($o['id'], [], '担当A');
    }

    public function testSplitZeroQuantityRejected(): void
    {
        $o = make_order('A001', 20);
        $this->expectException(BizException::class);
        order_split($o['id'], ['数量1' => 0, '納期1' => future_date(), '数量2' => 20, '納期2' => future_date()], '担当A');
    }

    public function testSplitMissingDeliveryDateRejected(): void
    {
        $o = make_order('A001', 20);
        $this->expectException(BizException::class);
        order_split($o['id'], ['数量1' => 20, '納期1' => ''], '担当A');
    }

    public function testSplitInboundOrderRejected(): void
    {
        $o = make_order('A001', 20);
        inbound_save([$o['id'] => '入庫'], '担当A');
        $this->expectException(BizException::class);
        order_split($o['id'], ['数量1' => 20, '納期1' => future_date()], '担当A');
    }

    public function testOrder区分Label(): void
    {
        $this->assertSame('通常', order_区分('H0000000001'));
        $this->assertSame('分割', order_区分('H0000000001-2'));
        $this->assertSame('未受付', order_status_label('未受付'));
        $this->assertSame('入庫済', order_status_label('入庫済'));
    }
}
