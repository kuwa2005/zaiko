<?php
declare(strict_types=1);

/**
 * 結合テスト：トランザクション原子性・部分反映の防止
 */
final class AtomicityTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        fresh_db();
    }

    public function testFailedOutboundLeavesNoPartialData(): void
    {
        make_item('A001', ['残数量' => 10]);
        try {
            outbound_register(['コード' => 'A001', '出庫数' => 999], '担当A');
            $this->fail('超過出庫が成功してしまった');
        } catch (BizException $e) {
            // 期待どおり
        }
        $this->assertCount(0, outbound_list());
        $this->assertSame(10.0, (float)db_val("SELECT 残数量 FROM 在庫マスタ WHERE コード='A001'"));
        $this->assertSame(0, (int)db_val("SELECT 連番 FROM 採番 WHERE 種別='出庫'"));
    }

    public function testNextNumberRollsBackInsideTransaction(): void
    {
        // 採番はトランザクション内でロールバックされると元に戻る
        $pdo = db();
        $pdo->beginTransaction();
        next_番号('手配');
        $pdo->rollBack();
        $this->assertSame(0, (int)db_val("SELECT 連番 FROM 採番 WHERE 種別='手配'"));

        // 改めて採番すると1から振られる
        $this->assertSame('H0000000001', next_番号('手配'));
    }

    public function testSplitFailureLeavesNoChildren(): void
    {
        make_item('A001');
        $o = make_order('A001', 20);
        try {
            order_split($o['id'], ['数量1' => 5, '納期1' => future_date()], '担当A');
            $this->fail('分割合計不一致が成功してしまった');
        } catch (BizException $e) {
            // 期待どおり
        }
        $this->assertSame(0, (int)db_val("SELECT COUNT(*) FROM 発注データ WHERE 注番 IS NOT NULL"));
        // 親は無傷
        $parent = db_one("SELECT * FROM 発注データ WHERE id = ?", [$o['id']]);
        $this->assertSame('未受付', $parent['ステータス']);
        $this->assertSame(0, (int)$parent['削除フラグ']);
    }

    public function testDraftCommitPartialFailureNotPossibleWhenEmpty(): void
    {
        // 下書きが空なら確定はエラーになり、発注データは増えない
        try {
            drafts_commit('担当A');
            $this->fail('空の確定が成功してしまった');
        } catch (BizException $e) {
            // 期待どおり
        }
        $this->assertCount(0, orders_list());
    }

    public function testConcurrentStyleNumberingStaysUnique(): void
    {
        make_item('A001');
        // 番号採番を連続で行い、重複がないことを確認（ロールバック後も含む）
        $pdo = db();
        $pdo->beginTransaction();
        next_番号('手配');
        next_番号('手配');
        $pdo->rollBack();

        $seen = [];
        for ($i = 0; $i < 10; $i++) {
            $seen[] = next_番号('手配');
        }
        $this->assertCount(10, array_unique($seen));
        $this->assertSame('H0000000001', $seen[0]);
        $this->assertSame('H0000000010', $seen[9]);
    }
}
