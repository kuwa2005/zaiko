<?php
declare(strict_types=1);

/**
 * 結合テスト：業務フローを通しで検証（在庫整合・ステータス遷移・採番一意性）
 */
final class FlowTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        fresh_db();
    }

    private function stockOf(string $code): float
    {
        return (float)db_val("SELECT 残数量 FROM 在庫マスタ WHERE コード = ?", [$code]);
    }

    public function testFullLifecycle(): void
    {
        // 1. 在庫マスタ登録
        make_item('A001', ['残数量' => 50, '基本数量' => 10, '単価' => 100, '安全在庫数' => 10]);

        // 2. 発注依頼 → 確定
        draft_add(['コード' => 'A001', '数量' => 30, '納期' => future_date(), '型式' => '', '備考' => ''], '担当A');
        $this->assertSame(1, drafts_commit('担当A'));

        // 3. 受付
        $o = db_one("SELECT * FROM 発注データ WHERE コード = 'A001'");
        receive_process([$o['id'] => '出力'], '担当B');
        $this->assertSame('受付済', order_find($o['id'])['ステータス']);

        // 4. 入庫（在庫 50 → 80）
        inbound_save([$o['id'] => '入庫'], '担当B');
        $this->assertSame(80.0, $this->stockOf('A001'));

        // 5. 出庫（在庫 80 → 70）
        $sid = outbound_register(['コード' => 'A001', '出庫数' => 10, '備考' => '納品'], '担当A');
        $this->assertSame(70.0, $this->stockOf('A001'));

        // 6. 月次締め（前月残 50 + 当入 30 - 当出 10 = 当残 70）
        close_month('担当B');
        $h = close_history()[0];
        $this->assertSame(50.0, (float)$h['前月残数量']);
        $this->assertSame(30.0, (float)$h['当入数量']);
        $this->assertSame(10.0, (float)$h['当出数量']);
        $this->assertSame(70.0, (float)$h['当残数量']);
        // 在庫恒等式
        $this->assertSame(70.0, (float)$h['前月残数量'] + (float)$h['当入数量'] - (float)$h['当出数量']);

        // 7. 操作ログが一連の操作で記録される
        $logs = db_all("SELECT 操作種別 FROM 操作ログ ORDER BY id");
        $kinds = array_column($logs, '操作種別');
        foreach (['在庫マスタ登録', '発注依頼確定', '受付確定', '入庫', '出庫登録', '月次締め'] as $expected) {
            $this->assertContains($expected, $kinds, "ログ種別 {$expected} が記録されていない");
        }
    }

    public function testSplitFlowKeepsBalance(): void
    {
        make_item('A001', ['残数量' => 10]);
        $o = make_order('A001', 20);
        $before = $this->stockOf('A001');

        // 親を 12+8 に分割
        $n = order_split($o['id'], ['数量1' => 12, '納期1' => future_date(5), '数量2' => 8, '納期2' => future_date(10)], '担当A');
        $this->assertSame(2, $n);

        // 子の片方を入庫
        $kids = db_all("SELECT * FROM 発注データ WHERE 注番 = ? AND 削除フラグ = 0", [$o['管理NO']]);
        inbound_save([$kids[0]['id'] => '入庫'], '担当A');

        // 12 入庫 → 在庫 +12
        $this->assertSame($before + 12, $this->stockOf('A001'));
        $this->assertSame('入庫済', order_find($kids[0]['id'])['ステータス']);
    }

    public function testStockNeverNegativeAfterRoundTrip(): void
    {
        make_item('A001', ['残数量' => 100]);

        // 出庫 → 変更 → 削除 → 再出庫 → 入庫取消 → 入庫 を繰り返しても負にならない
        $o = make_order('A001', 40);
        inbound_save([$o['id'] => '入庫'], '担当A');
        $this->assertSame(140.0, $this->stockOf('A001'));

        $sid = outbound_register(['コード' => 'A001', '出庫数' => 60], '担当A');
        outbound_update($sid, ['出庫数' => 30], '担当A');
        $this->assertSame(110.0, $this->stockOf('A001'));
        outbound_delete($sid, '担当A');
        $this->assertSame(140.0, $this->stockOf('A001'));

        inbound_save([$o['id'] => '入庫取消'], '担当A');
        $this->assertSame(100.0, $this->stockOf('A001'));
        inbound_save([$o['id'] => '入庫'], '担当A');
        $this->assertSame(140.0, $this->stockOf('A001'));
        $this->assertGreaterThanOrEqual(0.0, $this->stockOf('A001'));
    }

    public function testLogicalDeleteKeepsAuditTrail(): void
    {
        make_item('A001', ['残数量' => 10]);
        $o = make_order('A001', 20);
        $sid = outbound_register(['コード' => 'A001', '出庫数' => 5], '担当A');

        item_delete((int)db_val("SELECT id FROM 在庫マスタ WHERE コード='A001'"), '担当A');
        inbound_delete([$o['id'] => '削除'], '担当A');
        outbound_delete($sid, '担当A');

        // 一覧から消える
        $this->assertCount(0, items_list());
        $this->assertCount(0, inbound_list());
        $this->assertCount(0, outbound_list());

        // 物理レコードは残る（監査のため）
        $this->assertSame(1, (int)db_val("SELECT COUNT(*) FROM 在庫マスタ WHERE 削除フラグ=1"));
        $this->assertSame(1, (int)db_val("SELECT COUNT(*) FROM 発注データ WHERE 削除フラグ=1"));
        $this->assertSame(1, (int)db_val("SELECT COUNT(*) FROM 出庫データ WHERE 削除フラグ=1"));
    }

    public function test管理NOUniqueness(): void
    {
        make_item('A001');
        for ($i = 0; $i < 5; $i++) {
            draft_add(['コード' => 'A001', '数量' => 10, '納期' => future_date(), '型式' => '', '備考' => ''], '担当A');
        }
        drafts_commit('担当A');
        outbound_register(['コード' => 'A001', '出庫数' => 1], '担当A');
        outbound_register(['コード' => 'A001', '出庫数' => 1], '担当A');

        $all = array_merge(
            array_column(orders_list(), '管理NO'),
            array_column(outbound_list(), '管理NO')
        );
        $this->assertCount(7, $all);
        $this->assertCount(7, array_unique($all));
        foreach ($all as $no) {
            $this->assertMatchesRegularExpression('/^[HS]\d{10}$/', $no);
        }
    }

    public function testStatusTransitionChain(): void
    {
        make_item('A001');
        $o = make_order('A001', 20);
        $this->assertSame('未受付', order_find($o['id'])['ステータス']);

        receive_process([$o['id'] => '出力'], '担当A');
        $this->assertSame('受付済', order_find($o['id'])['ステータス']);

        inbound_save([$o['id'] => '入庫'], '担当A');
        $this->assertSame('入庫済', order_find($o['id'])['ステータス']);

        // 受付取消は無いが、入庫取消は受付済へ戻る
        inbound_save([$o['id'] => '入庫取消'], '担当A');
        $this->assertSame('受付済', order_find($o['id'])['ステータス']);
    }

    public function testMonthlyReportIsConsistentWithRealTimeBalance(): void
    {
        make_item('A001', ['残数量' => 40, '基本数量' => 10, '単価' => 100]);
        $o = make_order('A001', 30);
        inbound_save([$o['id'] => '入庫'], '担当A');
        outbound_register(['コード' => 'A001', '出庫数' => 12], '担当A');

        $data = monthly_rows();
        $r = $data['rows'][0];
        // リアルタイム残数量と月次当残が一致
        $this->assertSame($this->stockOf('A001'), $r['当残']);
        $this->assertSame(58.0, $r['当残']); // 40 + 30 - 12
        $this->assertSame(40.0, $r['前入']);
        // 金額: 前月残 40×10円=400 / 当残 58×10円=580
        $this->assertSame(400.0, $r['前入金額']);
        $this->assertSame(580.0, $r['当残金額']);
    }
}
