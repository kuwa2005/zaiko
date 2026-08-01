<?php
declare(strict_types=1);

// ---- テスト用DBの分離 ----
// テストプロセスごとに一時DBファイルへ向ける（本番/開発DBには触れない）
$test_db = sys_get_temp_dir() . '/zaiko_test_' . getmypid() . '.db';
putenv('ZAIKO_DB_PATH=' . $test_db);
putenv('ZAIKO_DEBUG=1');

require dirname(__DIR__) . '/app/bootstrap.php';

/** 各テスト前にテーブルを初期化してクリーンな状態にする */
function fresh_db(): void
{
    $pdo = db();
    $pdo->exec('PRAGMA foreign_keys = OFF');
    foreach (['操作ログ', '締め履歴', '出庫データ', '発注データ', '発注下書き', '在庫マスタ', '発注先マスタ', '出庫先マスタ', '採番', '担当者'] as $t) {
        $pdo->exec('DELETE FROM ' . $t);
    }
    $pdo->exec('PRAGMA foreign_keys = ON');

    $stmt = $pdo->prepare("INSERT INTO 採番 (種別, 連番) VALUES (?, 0)");
    $stmt->execute(['手配']);
    $stmt->execute(['出庫']);

    $stmt = $pdo->prepare("INSERT INTO 担当者 (名前) VALUES (?)");
    foreach (['担当A', '担当B', '担当C', '担当D', '担当E'] as $name) {
        $stmt->execute([$name]);
    }
}

/** テスト用の在庫マスタを1件登録して返す */
function make_item(string $コード = 'A001', array $over = []): array
{
    $in = array_merge([
        'コード' => $コード, '品名' => 'テスト品', '基本数量' => 10, '単位' => '個',
        '単価' => 100, '残数量' => 50, '安全在庫数' => 10, '最小発注数量' => 0,
        '標準納入日数' => '', '棚番' => '', '取引先' => '', '備考' => '',
    ], $over);
    item_register($in, '担当A');
    $item = item_find_by_code($コード);
    assert($item !== null);
    return $item;
}

/** テスト用の発注を下書き→確定で作って返す（受付前） */
function make_order(string $コード, float $数量, array $over = []): array
{
    draft_add(array_merge(['コード' => $コード, '数量' => $数量, '納期' => date('Y-m-d', strtotime('+30 days')), '型式' => '', '備考' => ''], $over), '担当A');
    drafts_commit('担当A');
    $o = db_one("SELECT * FROM 発注データ WHERE コード = ? ORDER BY id DESC", [$コード]);
    assert($o !== null);
    return $o;
}

/** テスト用の日付（今後30日） */
function future_date(int $days = 30): string
{
    return date('Y-m-d', strtotime("+{$days} days"));
}
