# block_calendar_csv_importer

Moodleのカレンダーイベントを CSVファイルで一括登録・削除するブロックプラグインです。

## 概要

時間割データ（mod_data）などからエクスポートしたCSVを使って、複数コースのカレンダーイベントをまとめてインポートできます。管理者が複数コースを一括処理する「管理者モード」と、教師が自分のコースのみ操作する「教師モード」を1プラグインで提供します。

### 主な機能

- **CSVによる一括登録・削除**（`create` / `delete` を行単位で指定）
- **プレビュー確認**：実行前に登録・削除・スキップ件数を表示
- **重複チェック**：同一コース・同一時刻のイベントが既存の場合はスキップ（上書きなし）
- **複数ヒット時の選択**：削除対象が複数ヒットした場合はチェックボックスで選択
- **courseid / courseidnumber 両対応**（管理者モード）
- **descriptionフォーマット選択**：プレーンテキスト / HTML / Markdown（非推奨）
- **BOM付きUTF-8対応**・クォート内改行対応

---

## 動作要件

- Moodle 4.0 以上

---

## インストール

1. `blocks/` ディレクトリにクローンまたは展開します：
   ```
   git clone https://github.com/yourname/block_calendar_csv_importer.git /path/to/moodle/blocks/calendar_csv_importer
   ```
   またはZIPをダウンロードして `blocks/calendar_csv_importer/` として配置します。

2. 管理者でMoodleにログインし、**サイト管理 → 通知** からインストールを完了します。

---

## アクセス方法

| 利用者 | アクセス経路 |
|---|---|
| 管理者・マネージャー | サイト管理 → プラグイン → ブロック → カレンダーCSVインポーター |
| 教師・編集教師 | コースページに本ブロックを設置 → リンクをクリック |

---

## CSVフォーマット

### 管理者モード

```csv
courseid,courseidnumber,action,title,timestart,timeend,location,description
7001,,create,【1】臨床薬理学 総論,2026-09-01 09:00,2026-09-01 10:30,第1講義室,担当：山田太郎
,2026_M_L1262-1,create,【2】臨床薬理学 各論,2026-09-08 09:00,2026-09-08 10:30,,担当：山田太郎
7001,,create,オリエンテーション,2026-09-01 08:30,,,全員参加
7001,,delete,【1】臨床薬理学 旧タイトル,2026-08-25 09:00,2026-08-25 10:30,,
```

### 教師モード

```csv
action,title,timestart,timeend,location,description
create,【1】臨床薬理学 総論,2026-09-01 09:00,2026-09-01 10:30,第1講義室,担当：山田太郎
create,オリエンテーション,2026-09-01 08:30,,,全員参加
delete,【1】臨床薬理学 旧タイトル,2026-08-25 09:00,2026-08-25 10:30,,
```

### カラム仕様

| カラム名 | 必須 | 形式 | 備考 |
|---|---|---|---|
| `courseid` | 管理者モード：どちらか一方必須 | 整数 | `course.id`（数値）。`courseidnumber` と併記時はこちら優先 |
| `courseidnumber` | 管理者モード：どちらか一方必須 | 文字列 | `course.idnumber`（例：`2026_M_L1262-1`） |
| `action` | 必須 | `create` / `delete` | 省略時は `create` 扱い |
| `title` | 必須 | 文字列 | 例：`【1】臨床薬理学 総論` |
| `timestart` | 必須 | `YYYY-MM-DD HH:MM` | |
| `timeend` | 任意 | `YYYY-MM-DD HH:MM` | 空欄なら「期間なし」（開始日時のみ） |
| `location` | 任意 | 文字列 | 教室名など。URLはクリッカブルにならない（Moodle仕様） |
| `description` | 任意 | 文字列 | 担当者・所属・URLなど |

### descriptionフォーマット

インポート画面のラジオボタンでCSV全体に一括指定します（列単位の指定は不可）。

| 選択肢 | 内容 |
|---|---|
| プレーンテキスト（デフォルト） | タグはそのまま文字表示。改行は `\n` が有効 |
| HTML | `<a href="...">` でリンク、`<br>` で改行など |
| Markdown（非推奨） | 改行は反映されるが、カレンダーUIでの完全なレンダリングは保証されない |

### 削除イベントの同定キー

`courseid` + `timestart` + `timeduration`（timeend - timestart）の3値で対象を特定します。`title` は同定キーに含まれないため、タイトル変更後でも削除できます。

---

## 処理フロー

```
CSVアップロード
　↓
パース・バリデーション（形式エラーは即中断）
　↓
プレビュー生成
　├── 登録予定 / 削除予定 / スキップ（既存あり）/ スキップ（対象なし）
　└── 複数ヒット → チェックボックスで削除対象を選択
　↓
「実行する」ボタンで確定
　↓
完了レポート（登録N件・削除N件・スキップN件・エラーN件）
```

---

## 権限

| ケイパビリティ | デフォルトロール | 用途 |
|---|---|---|
| `block/calendar_csv_importer:import` | 教師・編集教師・管理者・マネージャー | 教師モードでのインポート |
| `block/calendar_csv_importer:importany` | 管理者・マネージャー | 管理者モード（複数コース） |

---

## ファイル構成

```
block_calendar_csv_importer/
├── version.php
├── block_calendar_csv_importer.php   # ブロック本体
├── settings.php                      # サイト管理メニュー登録
├── import.php                        # アップロード・プレビュー・実行画面
├── locallib.php                      # パース・バリデーション・登録・削除ロジック
├── db/
│   └── access.php                    # 権限定義
└── lang/
    ├── en/block_calendar_csv_importer.php
    └── ja/block_calendar_csv_importer.php
```

---

## 設計の背景

自治医科大学における時間割管理システムの改善プロジェクトの一環として開発しました。時間割データをmod_dataで管理しつつ、カレンダービューでの表示・通知を実現するため、mod_dataからエクスポートしたCSVをカレンダーイベントに変換するツールが必要でした。

- **coure.idnumber対応**：CSVを手書きする際、数値IDより `idnumber` の方が直感的に扱えるため両対応
- **プレビュー＋確認フロー**：誤操作によるイベント重複・誤削除を防ぐため、実行前に必ずプレビューを挟む設計
- **fgetcsv採用**：descriptionに改行を含むケースに対応するため、str_getcsv（1行ずつ処理）ではなくfgetcsvでファイルハンドルを通して読み込む

---

## ライセンス

GNU General Public License v3.0
