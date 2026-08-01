<?php
declare(strict_types=1);

final class MonthlyTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        fresh_db();
        make_item('A001', ['残数量' => 60, '安全在庫数' => 20]);
    }

    public function testMonthlyRowCalculation(): void
    {
        // 初期残60 に 当月 入庫+30 / 出庫-10 → 当残80 / 前月残60
        $o = make_order('A001', 30);
        inbound_save([$o['id'] => '入庫'], '担当A');
        outbound_register(['コード' => 'A001', '出庫数' => 10], '担当A');

        $data = monthly_rows();
        $row = $data['rows'][0];
        $this->assertSame('A001', $row['コード']);
        // 前月残 = 当残 - 当入 + 当出 = 80 - 30 + 10
        $this->assertSame(60.0, $row['前入']);
        $this->assertSame(30.0, $row['当入']);
        $this->assertSame(10.0, $row['当出']);
        $this->assertSame(80.0, $row['当残']);
        // 金額 = 数量 / 基本数量 × 単価（基本10, 単価100）
        $this->assertSame(600.0, $row['前入金額']);
        $this->assertSame(800.0, $row['当残金額']);
    }

    public function testMonthlyTotals(): void
    {
        make_item('A002', ['残数量' => 5]);
        $o = make_order('A001', 30);
        inbound_save([$o['id'] => '入庫'], '担当A');
        outbound_register(['コード' => 'A001', '出庫数' => 10], '担当A');

        $data = monthly_rows();
        $t = $data['totals'];
        $this->assertCount(2, $data['rows']);
        $this->assertSame(65.0, $t['前入']); // 60 + 5
        $this->assertSame(30.0, $t['当入']);
        $this->assertSame(10.0, $t['当出']);
    }

    public function testWarningRules(): void
    {
        // 在庫0
        make_item('A002', ['残数量' => 0]);
        // 安全在庫以下（残5 < 安全10）
        make_item('A003', ['残数量' => 5, '安全在庫数' => 10]);
        // 発注予定あり
        make_order('A001', 10);

        $data = monthly_rows();
        $warn = [];
        foreach ($data['rows'] as $r) {
            $warn[$r['コード']] = $r['警告'];
        }
        $this->assertSame('在庫が0です。確認して下さい。', $warn['A002']);
        $this->assertSame('安全在庫以下です。発注して下さい。', $warn['A003']);
        $this->assertSame('発注予定又は発注済みです。', $warn['A001']);
    }

    public function testWarningList(): void
    {
        make_item('A002', ['残数量' => 0]);
        make_item('A003', ['残数量' => 5, '安全在庫数' => 10]);
        make_item('A004', ['残数量' => 50, '安全在庫数' => 10]);

        $codes = array_column(warning_list(), 'コード');
        sort($codes);
        $this->assertSame(['A002', 'A003'], $codes);
    }

    public function testCloseMonthRecordsSnapshot(): void
    {
        $o = make_order('A001', 30);
        inbound_save([$o['id'] => '入庫'], '担当A');
        $n = close_month('担当A');
        $this->assertSame(1, $n);

        $hist = close_history();
        $this->assertCount(1, $hist);
        $this->assertSame('A001', $hist[0]['コード']);
        $this->assertSame(60.0, (float)$hist[0]['前月残数量']);
        $this->assertSame(30.0, (float)$hist[0]['当入数量']);
        $this->assertSame(90.0, (float)$hist[0]['当残数量']);
        $this->assertSame('担当A', $hist[0]['締め者']);
        $this->assertSame(today(), $hist[0]['締め日']);
    }

    public function testCloseMonthReplacesPrevious(): void
    {
        $o = make_order('A001', 30);
        inbound_save([$o['id'] => '入庫'], '担当A');
        close_month('担当A');
        $n = close_month('担当B');
        $this->assertSame(1, $n);
        $this->assertCount(1, close_history());
        $this->assertSame('担当B', close_history()[0]['締め者']);
    }
}
