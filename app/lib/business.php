<?php
declare(strict_types=1);

// ---- 業務ルール例外 ----
class BizException extends Exception {}

// ============================================================
// 採番・正規化・金額
// ============================================================

/** コード正規化（全角→半角、大文字化）R4 */
function normalize_code(string $code): string
{
    $code = mb_convert_kana($code, 'a');   // 全角英数字→半角
    $code = mb_convert_kana($code, 's');   // 全角スペース→半角
    return strtoupper(trim($code));
}

/** 管理NO採番（Hxxxx / Sxxxx。DB採番・排他制御 C13） */
/** 管理NO採番（Hxxxx / Sxxxx。単一UPSERTで原子性を確保 C13） */
function next_番号(string $種別): string
{
    $stmt = db()->prepare(
        "INSERT INTO 採番 (種別, 連番) VALUES (?, 1)
         ON CONFLICT(種別) DO UPDATE SET 連番 = 連番 + 1
         RETURNING 連番"
    );
    $stmt->execute([$種別]);
    $n = (int)$stmt->fetchColumn();
    $prefix = $種別 === '出庫' ? 'S' : 'H';
    return $prefix . sprintf('%010d', $n);
}

/** 金額 = 数量 ÷ 基本数量 × 単価（R9・ゼロ除算ガード） */
function 金額(float $数量, float $基本数量, float $単価): float
{
    if ($基本数量 <= 0) {
        return 0.0;
    }
    return ($数量 / $基本数量) * $単価;
}

/** 今日の日付 yyyy-mm-dd */
function today(): string
{
    return date('Y-m-d');
}

/** 当月 yyyy-mm */
function current_month(): string
{
    return date('Y-m');
}

// ============================================================
// 在庫マスタ
// ============================================================

/** 在庫マスタ一覧（削除フラグ除外） */
function items_list(): array
{
    return db_all("SELECT * FROM 在庫マスタ WHERE 削除フラグ = 0 ORDER BY コード");
}

/** 在庫マスタ1件 */
function item_find(int $id): ?array
{
    return db_one("SELECT * FROM 在庫マスタ WHERE id = ? AND 削除フラグ = 0", [$id]);
}

function item_find_by_code(string $code): ?array
{
    return db_one("SELECT * FROM 在庫マスタ WHERE コード = ? AND 削除フラグ = 0", [normalize_code($code)]);
}

/** 在庫マスタ登録（F2・R4/R5/R6） */
function item_register(array $input, string $担当者): int
{
    $コード = normalize_code((string)($input['コード'] ?? ''));
    $品名   = trim((string)($input['品名'] ?? ''));
    $基本数量 = (float)($input['基本数量'] ?? 0);
    $単位   = trim((string)($input['単位'] ?? ''));
    $単価   = (float)($input['単価'] ?? 0);
    $残数量 = (float)($input['残数量'] ?? 0);
    $安全在庫数 = (float)($input['安全在庫数'] ?? 0);
    $最小発注数量 = (float)($input['最小発注数量'] ?? 0);
    $適正在庫数 = (float)($input['適正在庫数'] ?? 0);
    $標準納入日数 = ($input['標準納入日数'] ?? '') === '' ? null : (int)$input['標準納入日数'];
    $棚番   = trim((string)($input['棚番'] ?? ''));
    $取引先 = trim((string)($input['取引先'] ?? ''));
    $備考   = trim((string)($input['備考'] ?? ''));

    if ($コード === '') { throw new BizException('コードを入力して下さい。'); }
    if (item_find_by_code($コード) !== null) { throw new BizException('対象のコードは既に在庫マスタに登録されています。'); }
    if ($品名 === '') { throw new BizException('品名を入力して下さい。'); }
    if ($基本数量 <= 0) { throw new BizException('基本数量を入力して下さい。（0不可）'); }
    if ($単位 === '') { throw new BizException('単位を入力して下さい。'); }
    if ($単価 <= 0) { throw new BizException('単価を入力して下さい。（0不可）'); }
    if ($残数量 < 0) { throw new BizException('残数量は0以上で入力して下さい。'); }

    db_exec(
        "INSERT INTO 在庫マスタ (コード, 品名, 基本数量, 単位, 単価, 残数量, 安全在庫数, 最小発注数量, 適正在庫数,
                                  標準納入日数, 棚番, 取引先, 登録者, 登録日, 更新者, 更新日, 備考)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$コード, $品名, $基本数量, $単位, $単価, $残数量, $安全在庫数, $最小発注数量, $適正在庫数,
         $標準納入日数, $棚番, $取引先, $担当者, today(), $担当者, today(), $備考]
    );
    $id = db_last_id();
    log_op('在庫マスタ登録', '在庫マスタ', $コード);
    return $id;
}

/** 在庫マスタ変更（F3） */
function item_update(int $id, array $input, string $担当者): void
{
    $item = item_find($id);
    if ($item === null) { throw new BizException('対象データが見つかりません。'); }

    $コード = normalize_code((string)($input['コード'] ?? ''));
    $品名   = trim((string)($input['品名'] ?? ''));
    $基本数量 = (float)($input['基本数量'] ?? 0);
    $単位   = trim((string)($input['単位'] ?? ''));
    $単価   = (float)($input['単価'] ?? 0);
    $残数量 = (float)($input['残数量'] ?? 0);
    $安全在庫数 = (float)($input['安全在庫数'] ?? 0);
    $最小発注数量 = (float)($input['最小発注数量'] ?? 0);
    $適正在庫数 = (float)($input['適正在庫数'] ?? 0);
    $標準納入日数 = ($input['標準納入日数'] ?? '') === '' ? null : (int)$input['標準納入日数'];
    $棚番   = trim((string)($input['棚番'] ?? ''));
    $取引先 = trim((string)($input['取引先'] ?? ''));
    $備考   = trim((string)($input['備考'] ?? ''));

    if ($コード === '') { throw new BizException('コードを入力して下さい。'); }
    $dup = db_one("SELECT id FROM 在庫マスタ WHERE コード = ? AND id <> ? AND 削除フラグ = 0", [$コード, $id]);
    if ($dup) { throw new BizException('対象のコードは既に在庫マスタに登録されています。'); }
    if ($品名 === '') { throw new BizException('品名を入力して下さい。'); }
    if ($基本数量 <= 0) { throw new BizException('基本数量を入力して下さい。（0不可）'); }
    if ($単位 === '') { throw new BizException('単位を入力して下さい。'); }
    if ($単価 <= 0) { throw new BizException('単価を入力して下さい。（0不可）'); }
    if ($残数量 < 0) { throw new BizException('残数量は0以上で入力して下さい。'); }

    db_exec(
        "UPDATE 在庫マスタ SET コード=?, 品名=?, 基本数量=?, 単位=?, 単価=?, 残数量=?, 安全在庫数=?,
                最小発注数量=?, 適正在庫数=?, 標準納入日数=?, 棚番=?, 取引先=?, 更新者=?, 更新日=?, 備考=?
         WHERE id=?",
        [$コード, $品名, $基本数量, $単位, $単価, $残数量, $安全在庫数, $最小発注数量, $適正在庫数,
         $標準納入日数, $棚番, $取引先, $担当者, today(), $備考, $id]
    );
    log_op('在庫マスタ変更', '在庫マスタ', $コード);
}

/** 在庫マスタ論理削除（F3・C3） */
function item_delete(int $id, string $担当者): void
{
    $item = item_find($id);
    if ($item === null) { throw new BizException('対象データが見つかりません。'); }
    db_exec(
        "UPDATE 在庫マスタ SET 削除フラグ=1, 削除日=?, 削除者=? WHERE id=?",
        [today(), $担当者, $id]
    );
    log_op('在庫マスタ削除', '在庫マスタ', (string)$item['コード']);
}

// ============================================================
// 発注先マスタ（仕入れ先メーカー・卸）
// ============================================================

function supplier_list(): array
{
    return db_all("SELECT * FROM 発注先マスタ WHERE 削除フラグ = 0 ORDER BY 発注先コード");
}

function supplier_find(int $id): ?array
{
    return db_one("SELECT * FROM 発注先マスタ WHERE id = ? AND 削除フラグ = 0", [$id]);
}

function supplier_find_by_code(string $コード): ?array
{
    return db_one("SELECT * FROM 発注先マスタ WHERE 発注先コード = ? AND 削除フラグ = 0", [normalize_code($コード)]);
}

function supplier_register(array $input, string $担当者): int
{
    $発注先コード = normalize_code((string)($input['発注先コード'] ?? ''));
    $発注先名 = trim((string)($input['発注先名'] ?? ''));
    $住所   = trim((string)($input['住所'] ?? ''));
    $電話番号 = trim((string)($input['電話番号'] ?? ''));
    $先担当者 = trim((string)($input['担当者'] ?? ''));
    $備考   = trim((string)($input['備考'] ?? ''));

    if ($発注先コード === '') { throw new BizException('発注先コードを入力して下さい。'); }
    if (supplier_find_by_code($発注先コード) !== null) { throw new BizException('対象の発注先コードは既に登録されています。'); }
    if ($発注先名 === '') { throw new BizException('発注先名を入力して下さい。'); }

    db_exec(
        "INSERT INTO 発注先マスタ (発注先コード, 発注先名, 住所, 電話番号, 担当者, 備考, 登録者, 登録日, 更新者, 更新日)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$発注先コード, $発注先名, $住所, $電話番号, $先担当者, $備考, $担当者, today(), $担当者, today()]
    );
    $id = db_last_id();
    log_op('発注先登録', '発注先マスタ', $発注先コード);
    return $id;
}

function supplier_update(int $id, array $input, string $担当者): void
{
    $s = supplier_find($id);
    if ($s === null) { throw new BizException('対象データが見つかりません。'); }

    $発注先コード = normalize_code((string)($input['発注先コード'] ?? ''));
    $発注先名 = trim((string)($input['発注先名'] ?? ''));
    $住所   = trim((string)($input['住所'] ?? ''));
    $電話番号 = trim((string)($input['電話番号'] ?? ''));
    $先担当者 = trim((string)($input['担当者'] ?? ''));
    $備考   = trim((string)($input['備考'] ?? ''));

    if ($発注先コード === '') { throw new BizException('発注先コードを入力して下さい。'); }
    $dup = db_one("SELECT id FROM 発注先マスタ WHERE 発注先コード = ? AND id <> ? AND 削除フラグ = 0", [$発注先コード, $id]);
    if ($dup) { throw new BizException('対象の発注先コードは既に登録されています。'); }
    if ($発注先名 === '') { throw new BizException('発注先名を入力して下さい。'); }

    db_exec(
        "UPDATE 発注先マスタ SET 発注先コード=?, 発注先名=?, 住所=?, 電話番号=?, 担当者=?, 備考=?, 更新者=?, 更新日=?
         WHERE id=?",
        [$発注先コード, $発注先名, $住所, $電話番号, $先担当者, $備考, $担当者, today(), $id]
    );
    log_op('発注先変更', '発注先マスタ', $発注先コード);
}

function supplier_delete(int $id, string $担当者): void
{
    $s = supplier_find($id);
    if ($s === null) { throw new BizException('対象データが見つかりません。'); }
    db_exec(
        "UPDATE 発注先マスタ SET 削除フラグ=1, 削除日=?, 削除者=? WHERE id=?",
        [today(), $担当者, $id]
    );
    log_op('発注先削除', '発注先マスタ', (string)$s['発注先コード']);
}

// ============================================================
// 出庫先マスタ（販売先・小売店）
// ============================================================

function customer_list(): array
{
    return db_all("SELECT * FROM 出庫先マスタ WHERE 削除フラグ = 0 ORDER BY 出庫先コード");
}

function customer_find(int $id): ?array
{
    return db_one("SELECT * FROM 出庫先マスタ WHERE id = ? AND 削除フラグ = 0", [$id]);
}

function customer_find_by_code(string $コード): ?array
{
    return db_one("SELECT * FROM 出庫先マスタ WHERE 出庫先コード = ? AND 削除フラグ = 0", [normalize_code($コード)]);
}

function customer_register(array $input, string $担当者): int
{
    $出庫先コード = normalize_code((string)($input['出庫先コード'] ?? ''));
    $出庫先名 = trim((string)($input['出庫先名'] ?? ''));
    $住所   = trim((string)($input['住所'] ?? ''));
    $電話番号 = trim((string)($input['電話番号'] ?? ''));
    $先担当者 = trim((string)($input['担当者'] ?? ''));
    $備考   = trim((string)($input['備考'] ?? ''));

    if ($出庫先コード === '') { throw new BizException('出庫先コードを入力して下さい。'); }
    if (customer_find_by_code($出庫先コード) !== null) { throw new BizException('対象の出庫先コードは既に登録されています。'); }
    if ($出庫先名 === '') { throw new BizException('出庫先名を入力して下さい。'); }

    db_exec(
        "INSERT INTO 出庫先マスタ (出庫先コード, 出庫先名, 住所, 電話番号, 担当者, 備考, 登録者, 登録日, 更新者, 更新日)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$出庫先コード, $出庫先名, $住所, $電話番号, $先担当者, $備考, $担当者, today(), $担当者, today()]
    );
    $id = db_last_id();
    log_op('出庫先登録', '出庫先マスタ', $出庫先コード);
    return $id;
}

function customer_update(int $id, array $input, string $担当者): void
{
    $c = customer_find($id);
    if ($c === null) { throw new BizException('対象データが見つかりません。'); }

    $出庫先コード = normalize_code((string)($input['出庫先コード'] ?? ''));
    $出庫先名 = trim((string)($input['出庫先名'] ?? ''));
    $住所   = trim((string)($input['住所'] ?? ''));
    $電話番号 = trim((string)($input['電話番号'] ?? ''));
    $先担当者 = trim((string)($input['担当者'] ?? ''));
    $備考   = trim((string)($input['備考'] ?? ''));

    if ($出庫先コード === '') { throw new BizException('出庫先コードを入力して下さい。'); }
    $dup = db_one("SELECT id FROM 出庫先マスタ WHERE 出庫先コード = ? AND id <> ? AND 削除フラグ = 0", [$出庫先コード, $id]);
    if ($dup) { throw new BizException('対象の出庫先コードは既に登録されています。'); }
    if ($出庫先名 === '') { throw new BizException('出庫先名を入力して下さい。'); }

    db_exec(
        "UPDATE 出庫先マスタ SET 出庫先コード=?, 出庫先名=?, 住所=?, 電話番号=?, 担当者=?, 備考=?, 更新者=?, 更新日=?
         WHERE id=?",
        [$出庫先コード, $出庫先名, $住所, $電話番号, $先担当者, $備考, $担当者, today(), $id]
    );
    log_op('出庫先変更', '出庫先マスタ', $出庫先コード);
}

function customer_delete(int $id, string $担当者): void
{
    $c = customer_find($id);
    if ($c === null) { throw new BizException('対象データが見つかりません。'); }
    db_exec(
        "UPDATE 出庫先マスタ SET 削除フラグ=1, 削除日=?, 削除者=? WHERE id=?",
        [today(), $担当者, $id]
    );
    log_op('出庫先削除', '出庫先マスタ', (string)$c['出庫先コード']);
}

// ============================================================
// 発注下書き（発注依頼シート相当）
// ============================================================

function drafts_list(): array
{
    return db_all("SELECT * FROM 発注下書き ORDER BY id");
}

/** 発注下書き追加（F4） */
function draft_add(array $input, string $担当者): void
{
    $コード = normalize_code((string)($input['コード'] ?? ''));
    $品名   = trim((string)($input['品名'] ?? ''));
    $数量   = (float)($input['数量'] ?? 0);
    $納期   = $input['納期'] ?? '';
    $型式   = trim((string)($input['型式'] ?? ''));
    $発注先 = trim((string)($input['発注先'] ?? ''));
    $備考   = trim((string)($input['備考'] ?? ''));

    if ($コード === '') { throw new BizException('コードが選択されていません。'); }
    $item = item_find_by_code($コード);
    if ($item === null) { throw new BizException('選択したコードが見つかりません。'); }
    if ($品名 === '') { $品名 = (string)$item['品名']; }
    if ($発注先 === '') { $発注先 = trim((string)($item['取引先'] ?? '')); }
    if ($数量 <= 0) { throw new BizException('発注数量が入力されていません。'); }
    $最小発注数量 = (float)$item['最小発注数量'];
    if ($最小発注数量 > 0 && $数量 < $最小発注数量) {
        throw new BizException("発注数量 < 最小発注数量({$最小発注数量})です。発注数量を最小発注数量以上で発注依頼して下さい。");
    }
    if ($納期 === '') { throw new BizException('納期が入力されていません。'); }
    if ($納期 < date('Y-m-d', strtotime('-300 days')) || $納期 > date('Y-m-d', strtotime('+300 days'))) {
        throw new BizException('納期は本日±300日の範囲で指定して下さい。');
    }

    db_exec(
        "INSERT INTO 発注下書き (コード, 品名, 数量, 納期, 型式, 発注先, 依頼者, 依頼日, 備考)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$コード, $品名, $数量, $納期, $型式, $発注先, $担当者, today(), $備考]
    );
    log_op('発注下書き追加', '発注下書き', $コード);
}

/** 発注下書きの選択行削除（F5） */
function draft_delete(array $ids, string $担当者): void
{
    if (!$ids) { throw new BizException('削除するデータが選択されていません。'); }
    foreach ($ids as $id) {
        db_exec("DELETE FROM 発注下書き WHERE id = ?", [(int)$id]);
    }
    log_op('発注下書き削除', '発注下書き', implode(',', $ids));
}

/** 発注依頼確定：下書きを一括DB登録（F6・R16） */
function drafts_commit(string $担当者): int
{
    $drafts = drafts_list();
    if (!$drafts) { throw new BizException('発注依頼するデータが入力されていません。'); }

    $count = 0;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($drafts as $d) {
            $管理NO = next_番号('手配');
            db_exec(
                "INSERT INTO 発注データ (管理NO, コード, 品名, 数量, 納期, 型式, 発注先, 依頼者, 依頼日, ステータス, 備考, 更新日)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '未受付', ?, ?)",
                [$管理NO, $d['コード'], $d['品名'], $d['数量'], $d['納期'], $d['型式'],
                 $d['発注先'] ?? '', $d['依頼者'], $d['依頼日'], $d['備考'], today()]
            );
            db_exec("DELETE FROM 発注下書き WHERE id = ?", [(int)$d['id']]);
            $count++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    log_op('発注依頼確定', '発注データ', "{$count}件");
    return $count;
}

// ============================================================
// 発注データ
// ============================================================

/** 発注一覧（発注状況確認・在庫マスタ結合）F7 */
function orders_list(string $orderBy = '依頼日_desc'): array
{
    $order = match ($orderBy) {
        'コード'       => 'o.コード ASC',
        '管理NO_desc'  => 'o.管理NO DESC',
        '管理NO'       => 'o.管理NO ASC',
        default        => 'o.依頼日 DESC, o.id DESC',
    };
    return db_all(
        "SELECT o.*, IFNULL(i.基本数量,1) AS 基本数量, IFNULL(i.単位,'') AS 単位, IFNULL(i.単価,0) AS 単価
         FROM 発注データ o
         LEFT JOIN 在庫マスタ i ON i.コード = o.コード AND i.削除フラグ = 0
         WHERE o.削除フラグ = 0
         ORDER BY {$order}"
    );
}

/** コード別発注履歴（発注状況詳細 F16） */
function orders_by_code(string $コード): array
{
    return db_all(
        "SELECT o.*, IFNULL(i.基本数量,1) AS 基本数量, IFNULL(i.単位,'') AS 単位, IFNULL(i.単価,0) AS 単価
         FROM 発注データ o
         LEFT JOIN 在庫マスタ i ON i.コード = o.コード AND i.削除フラグ = 0
         WHERE o.コード = ? AND o.削除フラグ = 0
         ORDER BY o.依頼日 DESC, o.id DESC",
        [normalize_code($コード)]
    );
}

/** 発注1件 */
function order_find(int $id): ?array
{
    return db_one(
        "SELECT o.*, IFNULL(i.基本数量,1) AS 基本数量, IFNULL(i.単位,'') AS 単位, IFNULL(i.単価,0) AS 単価
         FROM 発注データ o
         LEFT JOIN 在庫マスタ i ON i.コード = o.コード AND i.削除フラグ = 0
         WHERE o.id = ? AND o.削除フラグ = 0",
        [$id]
    );
}

function order_find_by_no(string $管理NO): ?array
{
    return db_one(
        "SELECT * FROM 発注データ WHERE 管理NO = ? AND 削除フラグ = 0",
        [$管理NO]
    );
}

/** ステータス表示・バッジ用 */
function order_status_label(?string $s): string
{
    return match ($s) {
        '未受付' => '未受付',
        '受付済' => '受付済',
        '入庫済' => '入庫済',
        '分割済' => '分割済',
        default  => (string)$s,
    };
}

/** 区分（発注状況詳細のC列: 分割手配は「分割」） */
function order_区分(string $管理NO): string
{
    return str_contains($管理NO, '-') ? '分割' : '通常';
}

// ============================================================
// 受付（F8）
// ============================================================

/** 未受付一覧 */
function receive_list(): array
{
    return db_all("SELECT * FROM 発注データ WHERE 削除フラグ = 0 AND 受付日 IS NULL ORDER BY 依頼日 DESC, id DESC");
}

/**
 * 受付処理
 * @param array $marks [発注id => '出力'|'削除']
 */
function receive_process(array $marks, string $担当者): array
{
    $出力 = [];
    $削除 = [];
    foreach ($marks as $id => $m) {
        if ($m === '出力') { $出力[] = (int)$id; }
        elseif ($m === '削除') { $削除[] = (int)$id; }
    }

    $pdo = db();
    $pdo->beginTransaction();
    $n_out = 0;
    $n_del = 0;
    try {
        foreach ($出力 as $id) {
            $o = order_find($id);
            if ($o === null) { continue; }
            if ($o['受付日'] !== null) { continue; }
            db_exec(
                "UPDATE 発注データ SET 受付者=?, 受付日=?, ステータス='受付済', 更新日=? WHERE id=?",
                [$担当者, today(), today(), $id]
            );
            $n_out++;
            log_op('受付確定', '発注データ', (string)$o['管理NO']);
        }
        foreach ($削除 as $id) {
            $o = order_find($id);
            if ($o === null) { continue; }
            // 受付済・入庫済の削除は対象外（未受付のみ）→ 未受付以外はスキップ
            if ($o['受付日'] !== null) { continue; }
            db_exec(
                "UPDATE 発注データ SET 削除フラグ=1, 削除日=?, 削除者=?, ステータス='受付済' WHERE id=?",
                [today(), $担当者, $id]
            );
            $n_del++;
            log_op('発注削除', '発注データ', (string)$o['管理NO']);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    return ['出力' => $n_out, '削除' => $n_del];
}

// ============================================================
// 入庫（F10）
// ============================================================

/** 入庫一覧（全発注） */
function inbound_list(): array
{
    return db_all("SELECT * FROM 発注データ WHERE 削除フラグ = 0 ORDER BY 管理NO DESC");
}

/**
 * 入庫保存（入庫/入庫取消）R12
 * @param array $marks [発注id => '入庫'|'入庫取消'|'削除']
 */
function inbound_save(array $marks, string $担当者): array
{
    foreach ($marks as $m) {
        if ($m === '削除') {
            throw new BizException('内容欄に「削除」が入力されています。');
        }
    }

    $入庫 = [];
    $取消 = [];
    foreach ($marks as $id => $m) {
        if ($m === '入庫') { $入庫[] = (int)$id; }
        elseif ($m === '入庫取消') { $取消[] = (int)$id; }
    }

    $pdo = db();
    $pdo->beginTransaction();
    $n_in = 0;
    $n_cancel = 0;
    try {
        foreach ($入庫 as $id) {
            $o = order_find($id);
            if ($o === null) { continue; }
            if ($o['入庫日'] !== null) { continue; } // 入庫済みは対象外
            db_exec(
                "UPDATE 発注データ SET 入庫者=?, 入庫日=?, ステータス='入庫済', 更新日=? WHERE id=?",
                [$担当者, today(), today(), $id]
            );
            $n_in++;
            add_stock($o['コード'], (float)$o['数量']);
            log_op('入庫', '発注データ', (string)$o['管理NO'], "+{$o['数量']}");
        }
        foreach ($取消 as $id) {
            $o = order_find($id);
            if ($o === null) { continue; }
            if ($o['入庫日'] === null) { continue; } // 未入庫は取消不可
            db_exec(
                "UPDATE 発注データ SET 入庫者=NULL, 入庫日=NULL, ステータス='受付済', 更新日=? WHERE id=?",
                [today(), $id]
            );
            $n_cancel++;
            add_stock($o['コード'], -(float)$o['数量']);
            log_op('入庫取消', '発注データ', (string)$o['管理NO'], "-{$o['数量']}");
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    return ['入庫' => $n_in, '取消' => $n_cancel];
}

/**
 * 入庫削除（論理削除）。入庫済み行の削除は在庫を減算して整合を保つ
 * @param array $marks [発注id => '入庫'|'入庫取消'|'削除']
 */
function inbound_delete(array $marks, string $担当者): int
{
    foreach ($marks as $m) {
        if ($m === '入庫' || $m === '入庫取消') {
            throw new BizException("内容欄に「{$m}」が入力されています。");
        }
    }

    $削除 = [];
    foreach ($marks as $id => $m) {
        if ($m === '削除') { $削除[] = (int)$id; }
    }
    if (!$削除) { throw new BizException('削除するデータが選択されていません。'); }

    $pdo = db();
    $pdo->beginTransaction();
    $n_del = 0;
    try {
        foreach ($削除 as $id) {
            $o = order_find($id);
            if ($o === null) { continue; }
            $wasIn = $o['入庫日'] !== null;
            db_exec(
                "UPDATE 発注データ SET 削除フラグ=1, 削除日=?, 削除者=? WHERE id=?",
                [today(), $担当者, $id]
            );
            $n_del++;
            if ($wasIn) {
                add_stock($o['コード'], -(float)$o['数量']); // 入庫済み在庫の減算
            }
            log_op('発注削除', '発注データ', (string)$o['管理NO'], $wasIn ? '入庫済在庫減算' : '');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    return $n_del;
}

/** 在庫残数量の加減算（リアルタイム更新 C11） */
function add_stock(string $コード, float $delta): void
{
    db_exec(
        "UPDATE 在庫マスタ SET 残数量 = 残数量 + ? WHERE コード = ? AND 削除フラグ = 0",
        [$delta, normalize_code($コード)]
    );
}

// ============================================================
// 出庫（F11/F12）
// ============================================================

/** 出庫登録 */
function outbound_register(array $input, string $担当者): int
{
    $コード = normalize_code((string)($input['コード'] ?? ''));
    $出庫数 = (float)($input['出庫数'] ?? 0);
    $出庫先 = trim((string)($input['出庫先'] ?? ''));
    $備考   = trim((string)($input['備考'] ?? ''));

    $item = item_find_by_code($コード);
    if ($item === null) { throw new BizException('対象のコードが見つかりません。'); }
    if ($出庫数 <= 0) { throw new BizException('出庫数量が入力されていません。'); }
    $残数量 = (float)$item['残数量'];
    if ($出庫数 > $残数量) {
        throw new BizException("出庫数量が在庫残数量({$残数量})を超えています。");
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $管理NO = next_番号('出庫');
        db_exec(
            "INSERT INTO 出庫データ (管理NO, コード, 品名, 出庫数, 出庫先, 出庫者, 出庫日, 備考, 更新日)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$管理NO, $コード, $item['品名'], $出庫数, $出庫先, $担当者, today(), $備考, today()]
        );
        $id = db_last_id();
        add_stock($コード, -$出庫数);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    log_op('出庫登録', '出庫データ', $管理NO, "-{$出庫数}");
    return $id;
}

/** 出庫一覧 */
function outbound_list(): array
{
    return db_all("SELECT * FROM 出庫データ WHERE 削除フラグ = 0 ORDER BY 管理NO DESC");
}

function outbound_find(int $id): ?array
{
    return db_one("SELECT * FROM 出庫データ WHERE id = ? AND 削除フラグ = 0", [$id]);
}

/** 出庫変更（数量変更で在庫を調整） */
function outbound_update(int $id, array $input, string $担当者): void
{
    $s = outbound_find($id);
    if ($s === null) { throw new BizException('対象データが見つかりません。'); }
    $出庫数 = (float)($input['出庫数'] ?? 0);
    $出庫先 = trim((string)($input['出庫先'] ?? ''));
    $備考   = trim((string)($input['備考'] ?? ''));
    if ($出庫数 <= 0) { throw new BizException('出庫数量を入力して下さい。'); }

    $item = item_find_by_code((string)$s['コード']);
    $old = (float)$s['出庫数'];
    $current = $item ? (float)$item['残数量'] + $old : 0.0;
    if ($出庫数 > $current) {
        throw new BizException("出庫数量が在庫残数量({$current})を超えています。");
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_exec("UPDATE 出庫データ SET 出庫数=?, 出庫先=?, 備考=?, 更新日=? WHERE id=?",
            [$出庫数, $出庫先, $備考, today(), $id]);
        $delta = $old - $出庫数; // 残数量を戻して新しい出庫数分を引く
        add_stock((string)$s['コード'], $delta);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    log_op('出庫変更', '出庫データ', (string)$s['管理NO'], "{$old}→{$出庫数}");
}

/** 出庫削除（論理削除・在庫に戻す） */
function outbound_delete(int $id, string $担当者): void
{
    $s = outbound_find($id);
    if ($s === null) { throw new BizException('対象データが見つかりません。'); }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_exec("UPDATE 出庫データ SET 削除フラグ=1, 削除日=?, 削除者=? WHERE id=?",
            [today(), $担当者, $id]);
        add_stock((string)$s['コード'], (float)$s['出庫数']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    log_op('出庫削除', '出庫データ', (string)$s['管理NO'], "+{$s['出庫数']}");
}

// ============================================================
// 手配分割（F17・R8）
// ============================================================

/**
 * 手配データ分割。親を「分割済」にし、子(管理NO-1/2/3, 注番=親管理NO)を登録
 */
function order_split(int $orderId, array $parts, string $担当者): int
{
    $o = order_find($orderId);
    if ($o === null) { throw new BizException('対象データが見つかりません。'); }
    if ($o['入庫日'] !== null) { throw new BizException('入庫済みデータは分割できません。'); }

    $数量 = (float)$o['数量'];
    $sum = 0.0;
    $valid = [];
    foreach (['1', '2', '3'] as $n) {
        $q = trim((string)($parts['数量' . $n] ?? ''));
        $d = trim((string)($parts['納期' . $n] ?? ''));
        if ($q === '') { continue; }
        $qNum = (float)$q;
        if ($qNum <= 0) { throw new BizException("分割{$n}の数量が不正です。"); }
        if ($d === '') { throw new BizException("分割{$n}納期が未入力です。"); }
        $valid[$n] = ['数量' => $qNum, '納期' => $d];
        $sum += $qNum;
    }
    if (!$valid) { throw new BizException('分割数量が未入力です。'); }
    if (abs($sum - $数量) > 0.000001) {
        throw new BizException('手配数量 ≠ 分割数量1 + 分割数量2 + 分割数量3です。');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // 親を分割済としてクローズ
        db_exec(
            "UPDATE 発注データ SET ステータス='分割済', 削除フラグ=1, 削除日=?, 削除者=?, 更新日=? WHERE id=?",
            [today(), $担当者, today(), $orderId]
        );
        foreach ($valid as $n => $p) {
            db_exec(
                "INSERT INTO 発注データ (管理NO, 注番, コード, 品名, 数量, 納期, 型式, 依頼者, 依頼日, 受付者, 受付日, ステータス, 備考, 更新日)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '未受付', ?, ?)",
                [(string)$o['管理NO'] . '-' . $n, (string)$o['管理NO'], $o['コード'], $o['品名'], $p['数量'],
                 $p['納期'], $o['型式'], $o['依頼者'], $o['依頼日'], $o['受付者'], $o['受付日'], $o['備考'], today()]
            );
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    log_op('手配分割', '発注データ', (string)$o['管理NO'], count($valid) . '分割');
    return count($valid);
}

// ============================================================
// 入出庫状況確認・月次締め（F13/F14）
// ============================================================

/** 月次集計（前月残/当月入庫/当月出庫/当月残・警告） */
function monthly_rows(string $月 = null): array
{
    $月 = $月 ?? current_month();
    $like = $月 . '-%';

    $items = db_all("SELECT * FROM 在庫マスタ WHERE 削除フラグ = 0 ORDER BY コード");
    $rows = [];
    $totals = ['前入' => 0.0, '前入金額' => 0.0, '当入' => 0.0, '当入金額' => 0.0,
               '当出' => 0.0, '当出金額' => 0.0, '当残' => 0.0, '当残金額' => 0.0];

    foreach ($items as $it) {
        $当入 = (float)db_val(
            "SELECT IFNULL(SUM(数量),0) FROM 発注データ WHERE コード=? AND 入庫日 IS NOT NULL AND 入庫日 LIKE ? AND 削除フラグ=0",
            [$it['コード'], $like], 0.0
        );
        $当出 = (float)db_val(
            "SELECT IFNULL(SUM(出庫数),0) FROM 出庫データ WHERE コード=? AND 出庫日 LIKE ? AND 削除フラグ=0",
            [$it['コード'], $like], 0.0
        );
        $残数量 = (float)$it['残数量'];
        // リアルタイム残高から月初残を逆算
        $前入 = $残数量 - $当入 + $当出;
        $単価 = (float)$it['単価'];
        $基本 = (float)$it['基本数量'];

        $警告 = '';
        if ($残数量 <= 0) {
            $警告 = '在庫が0です。確認して下さい。';
        } elseif ($残数量 < (float)$it['安全在庫数']) {
            $警告 = '安全在庫以下です。発注して下さい。';
        }

        // 発注予定（未入庫の発注が存在）
        $hasOrder = (int)db_val(
            "SELECT COUNT(*) FROM 発注データ WHERE コード=? AND 入庫日 IS NULL AND 削除フラグ=0",
            [$it['コード']], 0
        );
        if ($hasOrder > 0 && $警告 === '') {
            $警告 = '発注予定又は発注済みです。';
        }

        $row = [
            'コード' => $it['コード'], '品名' => $it['品名'], '基本数量' => $基本, '単位' => $it['単位'],
            '単価' => $単価, '安全在庫数' => $it['安全在庫数'], '棚番' => $it['棚番'],
            '前入' => $前入, '前入金額' => 金額($前入, $基本, $単価),
            '当入' => $当入, '当入金額' => 金額($当入, $基本, $単価),
            '当出' => $当出, '当出金額' => 金額($当出, $基本, $単価),
            '当残' => $残数量, '当残金額' => 金額($残数量, $基本, $単価),
            '警告' => $警告,
        ];
        $rows[] = $row;

        $totals['前入'] += $row['前入']; $totals['前入金額'] += $row['前入金額'];
        $totals['当入'] += $row['当入']; $totals['当入金額'] += $row['当入金額'];
        $totals['当出'] += $row['当出']; $totals['当出金額'] += $row['当出金額'];
        $totals['当残'] += $row['当残']; $totals['当残金額'] += $row['当残金額'];
    }
    return ['rows' => $rows, 'totals' => $totals];
}

/** 月次締め（前月残数の記録・期首残スナップショット）F14 */
function close_month(string $担当者, string $月 = null): int
{
    $月 = $月 ?? current_month();
    $data = monthly_rows($月);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_exec("DELETE FROM 締め履歴 WHERE 対象月 = ?", [$月]);
        foreach ($data['rows'] as $r) {
            db_exec(
                "INSERT INTO 締め履歴 (対象月, コード, 品名, 前月残数量, 当入数量, 当出数量, 当残数量, 締め日, 締め者)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$月, $r['コード'], $r['品名'], $r['前入'], $r['当入'], $r['当出'], $r['当残'], today(), $担当者]
            );
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    log_op('月次締め', '締め履歴', $月, count($data['rows']) . '件');
    return count($data['rows']);
}

/** 締め履歴一覧 */
function close_history(string $月 = null): array
{
    $月 = $月 ?? current_month();
    return db_all("SELECT * FROM 締め履歴 WHERE 対象月 = ? ORDER BY コード", [$月]);
}

/** 警告一覧（ダッシュボード用 F15） */
function warning_list(): array
{
    return db_all(
        "SELECT コード, 品名, 残数量, 安全在庫数 FROM 在庫マスタ
         WHERE 削除フラグ = 0 AND (残数量 <= 0 OR 残数量 < 安全在庫数)
         ORDER BY コード"
    );
}
