<?php
declare(strict_types=1);

/** PDO インスタンス取得（シングルトン） */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

/** 1行取得 */
function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** 複数行取得 */
function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** スカラー取得 */
function db_val(string $sql, array $params = [], $default = null)
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $val = $stmt->fetchColumn();
    return $val === false ? $default : $val;
}

/** 実行のみ */
function db_exec(string $sql, array $params = []): void
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
}

/** 最後の insert id */
function db_last_id(): int
{
    return (int)db()->lastInsertId();
}
