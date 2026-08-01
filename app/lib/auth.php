<?php
declare(strict_types=1);

// ---- 担当者（操作者）管理 ----

/** 現在の担当者を取得（未選択なら null） */
function current_担当者(): ?string
{
    $name = $_SESSION['担当者'] ?? null;
    return is_string($name) && $name !== '' ? $name : null;
}

/** 担当者チェック。未選択ならログインページへ */
function require_担当者(): void
{
    if (current_担当者() === null) {
        redirect(url('login'));
    }
}

/** 担当者一覧 */
function 担当者一覧(): array
{
    return db_all("SELECT * FROM 担当者 ORDER BY id");
}

/** 担当者選択をセッションへ保存 */
function set_担当者(string $name): void
{
    $_SESSION['担当者'] = $name;
}
