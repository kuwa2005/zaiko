<?php
declare(strict_types=1);

final class MasterTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        fresh_db();
        make_item('A001');
    }

    // ---- 発注先マスタ ----

    public function testSupplierRegisterAndFind(): void
    {
        $id = supplier_register(['発注先コード' => 'SP001', '発注先名' => 'テストメーカー'], '担当A');
        $s = supplier_find($id);
        $this->assertNotNull($s);
        $this->assertSame('SP001', $s['発注先コード']);
        $this->assertSame('テストメーカー', $s['発注先名']);
        $this->assertSame('担当A', $s['登録者']);
    }

    public function testSupplierDuplicateCodeRejected(): void
    {
        supplier_register(['発注先コード' => 'SP001', '発注先名' => 'A社'], '担当A');
        $this->expectException(BizException::class);
        supplier_register(['発注先コード' => 'sp001', '発注先名' => 'B社'], '担当A');
    }

    public function testSupplierNameRequired(): void
    {
        $this->expectException(BizException::class);
        supplier_register(['発注先コード' => 'SP001', '発注先名' => ''], '担当A');
    }

    public function testSupplierUpdateAndDelete(): void
    {
        $id = supplier_register(['発注先コード' => 'SP001', '発注先名' => 'A社'], '担当A');
        supplier_update($id, ['発注先コード' => 'SP002', '発注先名' => 'B社'], '担当B');
        $this->assertSame('SP002', supplier_find($id)['発注先コード']);
        $this->assertSame('B社', supplier_find($id)['発注先名']);

        supplier_delete($id, '担当B');
        $this->assertNull(supplier_find($id));
        $this->assertCount(0, supplier_list());
    }

    // ---- 出庫先マスタ ----

    public function testCustomerRegisterAndList(): void
    {
        customer_register(['出庫先コード' => 'ST001', '出庫先名' => 'テスト書店'], '担当A');
        customer_register(['出庫先コード' => 'ST002', '出庫先名' => 'テスト文具店'], '担当A');
        $this->assertCount(2, customer_list());
        $this->assertSame('ST001', customer_list()[0]['出庫先コード']);
    }

    public function testCustomerDuplicateRejected(): void
    {
        customer_register(['出庫先コード' => 'ST001', '出庫先名' => 'A店'], '担当A');
        $this->expectException(BizException::class);
        customer_register(['出庫先コード' => 'ST001', '出庫先名' => 'B店'], '担当A');
    }

    public function testCustomerDelete(): void
    {
        $id = customer_register(['出庫先コード' => 'ST001', '出庫先名' => 'A店'], '担当A');
        customer_delete($id, '担当A');
        $this->assertNull(customer_find($id));
    }

    // ---- 発注依頼のマスタ連動（発注先） ----

    public function testDraftCarriesSupplierFromItem(): void
    {
        supplier_register(['発注先コード' => 'SP001', '発注先名' => 'テストメーカー'], '担当A');
        item_update(item_find_by_code('A001')['id'], ['取引先' => 'テストメーカー'] + item_register_input('A001'), '担当A');

        draft_add(['コード' => 'A001', '数量' => 10, '納期' => future_date()], '担当B');
        $d = drafts_list()[0];
        $this->assertSame('テストメーカー', $d['発注先']);

        drafts_commit('担当B');
        $o = db_one("SELECT * FROM 発注データ ORDER BY id DESC LIMIT 1");
        $this->assertSame('テストメーカー', $o['発注先']);
    }

    public function testDraftExplicitSupplierWins(): void
    {
        supplier_register(['発注先コード' => 'SP001', '発注先名' => 'A社'], '担当A');
        draft_add(['コード' => 'A001', '数量' => 10, '納期' => future_date(), '発注先' => 'A社'], '担当B');
        $this->assertSame('A社', drafts_list()[0]['発注先']);
    }

    // ---- 出庫のマスタ連動（出庫先） ----

    public function testOutboundCarriesCustomer(): void
    {
        customer_register(['出庫先コード' => 'ST001', '出庫先名' => 'テスト書店'], '担当A');
        $id = outbound_register(['コード' => 'A001', '出庫数' => 5, '出庫先' => 'テスト書店'], '担当A');
        $this->assertSame('テスト書店', outbound_find($id)['出庫先']);
    }

    public function testOutboundUpdateChangesCustomer(): void
    {
        customer_register(['出庫先コード' => 'ST001', '出庫先名' => 'A書店'], '担当A');
        customer_register(['出庫先コード' => 'ST002', '出庫先名' => 'B文具店'], '担当A');
        $id = outbound_register(['コード' => 'A001', '出庫数' => 5, '出庫先' => 'A書店'], '担当A');
        outbound_update($id, ['出庫数' => 5, '出庫先' => 'B文具店'], '担当B');
        $this->assertSame('B文具店', outbound_find($id)['出庫先']);
    }
}

/** item_update 用に現行値を丸ごと渡すための入力組み立て */
function item_register_input(string $コード): array
{
    $it = item_find_by_code($コード);
    assert($it !== null);
    return [
        'コード' => $it['コード'], '品名' => $it['品名'], '基本数量' => $it['基本数量'], '単位' => $it['単位'],
        '単価' => $it['単価'], '残数量' => $it['残数量'], '安全在庫数' => $it['安全在庫数'],
        '最小発注数量' => $it['最小発注数量'], '適正在庫数' => $it['適正在庫数'],
        '標準納入日数' => $it['標準納入日数'], '棚番' => $it['棚番'], '備考' => $it['備考'],
    ];
}
