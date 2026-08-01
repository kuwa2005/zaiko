<?php
declare(strict_types=1);

final class DraftTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        fresh_db();
        make_item('A001', ['最小発注数量' => 10]);
    }

    private function draftInput(array $over = []): array
    {
        return array_merge([
            'コード' => 'A001', '数量' => 30, '納期' => future_date(), '型式' => 'T-1', '備考' => '',
        ], $over);
    }

    public function testAddSuccessAndAutoFillName(): void
    {
        draft_add($this->draftInput(), '担当A');
        $d = db_one("SELECT * FROM 発注下書き WHERE コード = 'A001'");
        $this->assertNotNull($d);
        $this->assertSame('テスト品', $d['品名']);
        $this->assertSame('30', (string)$d['数量']);
        $this->assertSame('担当A', $d['依頼者']);
    }

    public function testAddInvalidCodeRejected(): void
    {
        $this->expectException(BizException::class);
        draft_add($this->draftInput(['コード' => 'ZZZ']), '担当A');
    }

    public function testAddZeroQuantityRejected(): void
    {
        $this->expectException(BizException::class);
        draft_add($this->draftInput(['数量' => 0]), '担当A');
    }

    public function testAddBelowMinimumOrderQtyRejected(): void
    {
        $this->expectException(BizException::class);
        draft_add($this->draftInput(['数量' => 5]), '担当A'); // 最小発注数量=10
    }

    public function testAddRequiresDeliveryDate(): void
    {
        $this->expectException(BizException::class);
        draft_add($this->draftInput(['納期' => '']), '担当A');
    }

    public function testAddDeliveryDateOutOfRangeRejected(): void
    {
        $this->expectException(BizException::class);
        draft_add($this->draftInput(['納期' => '2020-01-01']), '担当A');
    }

    public function testDeleteSelectedRows(): void
    {
        draft_add($this->draftInput(), '担当A');
        draft_add($this->draftInput(['数量' => 20]), '担当A');
        $ids = array_column(drafts_list(), 'id');
        draft_delete([$ids[0]], '担当A');
        $this->assertCount(1, drafts_list());
    }

    public function testDeleteEmptyThrows(): void
    {
        $this->expectException(BizException::class);
        draft_delete([], '担当A');
    }

    public function testCommitCreatesOrdersAndClearsDrafts(): void
    {
        draft_add($this->draftInput(['数量' => 30]), '担当A');
        draft_add($this->draftInput(['数量' => 20]), '担当A');
        $n = drafts_commit('担当A');
        $this->assertSame(2, $n);
        $this->assertCount(0, drafts_list());
        $orders = orders_list();
        $this->assertCount(2, $orders);
        $this->assertStringStartsWith('H', $orders[0]['管理NO']);
        $this->assertSame('未受付', $orders[0]['ステータス']);
        $this->assertSame('担当A', $orders[0]['依頼者']);
    }

    public function testCommitEmptyThrows(): void
    {
        $this->expectException(BizException::class);
        drafts_commit('担当A');
    }

    public function testCommitAssignsSequentialNumbers(): void
    {
        draft_add($this->draftInput(['数量' => 30]), '担当A');
        draft_add($this->draftInput(['数量' => 20]), '担当A');
        drafts_commit('担当A');
        $nos = array_column(orders_list(), '管理NO');
        sort($nos);
        $this->assertSame(['H0000000001', 'H0000000002'], $nos);
    }
}
