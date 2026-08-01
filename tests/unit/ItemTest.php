<?php
declare(strict_types=1);

final class ItemTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        fresh_db();
    }

    private function baseInput(array $over = []): array
    {
        return array_merge([
            'コード' => 'A001', '品名' => 'ねじ', '基本数量' => 100, '単位' => '箱',
            '単価' => 1000, '残数量' => 50, '安全在庫数' => 20, '最小発注数量' => 10,
            '標準納入日数' => '', '棚番' => 'A1', '取引先' => 'X社', '備考' => '',
        ], $over);
    }

    public function testRegisterSuccess(): void
    {
        $id = item_register($this->baseInput(), '担当A');
        $item = item_find($id);
        $this->assertNotNull($item);
        $this->assertSame('A001', $item['コード']);
        $this->assertSame('担当A', $item['登録者']);
        $this->assertSame('ねじ', $item['品名']);
        $this->assertSame('1000', (string)$item['単価']);
    }

    public function testRegisterNormalizesCode(): void
    {
        item_register($this->baseInput(['コード' => 'ａ−００１']), '担当A');
        $this->assertNotNull(item_find_by_code('A-001'));
    }

    public function testRegisterDuplicateCodeRejected(): void
    {
        item_register($this->baseInput(), '担当A');
        $this->expectException(BizException::class);
        item_register($this->baseInput(), '担当A');
    }

    public function testRegisterValidation(): void
    {
        $cases = [
            ['コード' => '', '品名' => 'x', '基本数量' => 1, '単位' => '箱', '単価' => 1, '残数量' => 0],
            ['コード' => 'X1', '品名' => '', '基本数量' => 1, '単位' => '箱', '単価' => 1, '残数量' => 0],
            ['コード' => 'X1', '品名' => 'x', '基本数量' => 0, '単位' => '箱', '単価' => 1, '残数量' => 0],
            ['コード' => 'X1', '品名' => 'x', '基本数量' => 1, '単位' => '', '単価' => 1, '残数量' => 0],
            ['コード' => 'X1', '品名' => 'x', '基本数量' => 1, '単位' => '箱', '単価' => 0, '残数量' => 0],
            ['コード' => 'X1', '品名' => 'x', '基本数量' => 1, '単位' => '箱', '単価' => 1, '残数量' => -1],
        ];
        foreach ($cases as $i => $c) {
            try {
                item_register($c, '担当A');
                $this->fail("case {$i} が成功してしまった");
            } catch (BizException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRegisterStoresTargetStock(): void
    {
        $id = item_register($this->baseInput(['適正在庫数' => 40]), '担当A');
        $item = item_find($id);
        $this->assertSame('40', (string)$item['適正在庫数']);
        item_update($id, $this->baseInput(['適正在庫数' => 60]), '担当B');
        $this->assertSame('60', (string)item_find($id)['適正在庫数']);
    }

    public function testUpdateSuccess(): void
    {
        $id = item_register($this->baseInput(), '担当A');
        item_update($id, $this->baseInput(['品名' => '六角ねじ', '単価' => 1200]), '担当B');
        $item = item_find($id);
        $this->assertSame('六角ねじ', $item['品名']);
        $this->assertSame('1200', (string)$item['単価']);
        $this->assertSame('担当B', $item['更新者']);
    }

    public function testUpdateDuplicateRejected(): void
    {
        item_register($this->baseInput(['コード' => 'A001']), '担当A');
        $id2 = item_register($this->baseInput(['コード' => 'A002']), '担当A');
        $this->expectException(BizException::class);
        item_update($id2, $this->baseInput(['コード' => 'A001']), '担当A');
    }

    public function testUpdateSelfCodeAllowed(): void
    {
        $id = item_register($this->baseInput(), '担当A');
        item_update($id, $this->baseInput(), '担当A');
        $this->assertNotNull(item_find($id));
    }

    public function testDeleteIsLogical(): void
    {
        $id = item_register($this->baseInput(), '担当A');
        item_delete($id, '担当A');
        $this->assertNull(item_find($id));
        $this->assertNull(item_find_by_code('A001'));
        $this->assertCount(0, items_list());
        // 物理的には残っている（削除フラグ）
        $row = db_one("SELECT * FROM 在庫マスタ WHERE id = ?", [$id]);
        $this->assertSame(1, (int)$row['削除フラグ']);
        $this->assertSame('担当A', $row['削除者']);
    }

    public function testDeleteMissingItemThrows(): void
    {
        $this->expectException(BizException::class);
        item_delete(999, '担当A');
    }
}
