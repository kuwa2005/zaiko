# UserForm フォーム仕様

## 発注登録画面.frm

**デザイナーの自動生成ラベル**: Label1, Label2, Label28, Label33, Label4, Label5, Label8, Label9

| コントロール | 推測種別 | イベント | コード上で利用するプロパティ |
|---|---|---|---|
| クリア | CommandButton(ボタン) | Click | - |
| コード | ComboBox(コンボボックス) | Change | ColumnCount,ColumnHeads,ColumnWidths,ListWidth,RowSource,SetFocus |
| 型式 | TextBox(テキストボックス) | - | SetFocus |
| 数量 | TextBox(テキストボックス) | - | SetFocus |
| 登録 | CommandButton(ボタン) | Click | - |
| 納期 | ComboBox(コンボボックス) | Change | AddItem,Clear,SetFocus |
| 閉じる | CommandButton(ボタン) | Click | - |

**フォーム固有プロシージャ**: 発注登録画面閉じる, 品名及び最小発注数量セット, 発注登録データチェック, 発注登録画面クリア, 手配管理NO採番, 発注登録データセット

## 出庫変更画面.frm

**デザイナーの自動生成ラベル**: Label29, Label33, Label8

| コントロール | 推測種別 | イベント | コード上で利用するプロパティ |
|---|---|---|---|
| 出庫数量 | TextBox(テキストボックス) | - | SetFocus |
| 削除 | CommandButton(ボタン) | Click | - |
| 変更 | CommandButton(ボタン) | Click | - |
| 閉じる | CommandButton(ボタン) | Click | - |

**フォーム固有プロシージャ**: 出庫変更画面を閉じる, 出庫確認データチェック, 出庫確認データセット

## 手配データ分割画面.frm

**デザイナーの自動生成ラベル**: Label13, Label2, Label21, Label23, Label25, Label29, Label30, Label31, Label32, Label33, Label34, Label35

| コントロール | 推測種別 | イベント | コード上で利用するプロパティ |
|---|---|---|---|
| 分割数量1 | TextBox(テキストボックス) | - | SetFocus |
| 分割納期1 | ComboBox(コンボボックス) | - | AddItem,SetFocus |
| 分割納期2 | ComboBox(コンボボックス) | - | AddItem,SetFocus |
| 分割納期3 | ComboBox(コンボボックス) | - | AddItem,SetFocus |
| 実行 | CommandButton(ボタン) | Click | - |
| 閉じる | CommandButton(ボタン) | Click | - |

**フォーム固有プロシージャ**: 手配分割データ登録, 手配データ分割画面チェック, 手配データ分割画面を閉じる

## 在庫マスタ変更画面.frm

**デザイナーの自動生成ラベル**: Label12, Label14, Label25, Label27, Label29, Label3, Label30, Label32, Label33, Label34, Label36, Label37, Label38, Label78, Label8, Label9

| コントロール | 推測種別 | イベント | コード上で利用するプロパティ |
|---|---|---|---|
| 削除 | CommandButton(ボタン) | Click | - |
| 単位 | ComboBox(コンボボックス) | - | AddItem,Clear,SetFocus |
| 単価 | TextBox(テキストボックス) | - | SetFocus |
| 品名 | TextBox(テキストボックス) | - | SetFocus |
| 変更 | CommandButton(ボタン) | Click | - |
| 数量 | TextBox(テキストボックス) | - | SetFocus |
| 残数量 | TextBox(テキストボックス) | - | SetFocus |
| 閉じる | CommandButton(ボタン) | Click | - |

**フォーム固有プロシージャ**: 在庫マスタ変更データセット, 在庫マスタ変更データチェック, 在庫マスタ変更画面を閉じる

## 在庫マスタ登録画面.frm

**デザイナーの自動生成ラベル**: Label14, Label16, Label21, Label23, Label29, Label30, Label31, Label33, Label36, Label38, Label64, Label7, Label9

| コントロール | 推測種別 | イベント | コード上で利用するプロパティ |
|---|---|---|---|
| コード | TextBox(テキストボックス) | - | SetFocus |
| 単位 | ComboBox(コンボボックス) | - | AddItem,Clear,SetFocus |
| 単価 | TextBox(テキストボックス) | - | SetFocus |
| 品名 | TextBox(テキストボックス) | - | SetFocus |
| 数量 | TextBox(テキストボックス) | - | SetFocus |
| 残数量 | TextBox(テキストボックス) | - | SetFocus |
| 登録 | CommandButton(ボタン) | Click | - |
| 閉じる | CommandButton(ボタン) | Click | - |

**フォーム固有プロシージャ**: 在庫マスタ登録画面クリア, 在庫マスタ登録データセット, 在庫マスタ登録データチェック, 在庫マスタ登録画面を閉じる

## 出庫登録画面.frm

**デザイナーの自動生成ラベル**: Label19, Label27, Label29, Label61, Label70

| コントロール | 推測種別 | イベント | コード上で利用するプロパティ |
|---|---|---|---|
| 出庫数量 | TextBox(テキストボックス) | - | SetFocus |
| 登録 | CommandButton(ボタン) | Click | - |
| 閉じる | CommandButton(ボタン) | Click | - |

**フォーム固有プロシージャ**: 出庫データチェック, 出庫登録画面を閉じる, 出庫管理NO採番, 出庫データセット

