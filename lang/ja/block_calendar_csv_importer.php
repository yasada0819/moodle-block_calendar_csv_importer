<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']                        = 'カレンダーCSVインポーター';
$string['calendar_csv_importer:import']      = 'コースカレンダーへのCSVインポート';
$string['calendar_csv_importer:importany']   = '任意コースのカレンダーへのCSVインポート';

// Block UI
$string['openimporter']                      = 'カレンダーCSVインポーター';
$string['adminmode']                         = '管理者モード（複数コース）';
$string['teachermode']                       = '教師モード（このコース）';

// Import page
$string['importtitle']                       = 'カレンダーCSVインポート';
$string['uploadcsv']                         = 'CSVファイルをアップロード';
$string['descformat']                        = '説明フィールドのフォーマット';
$string['descformat_plain']                  = 'プレーンテキスト';
$string['descformat_html']                   = 'HTML';
$string['descformat_markdown']               = 'Markdown（表示保証なし・非推奨）';
$string['preview']                           = 'プレビューを確認する';
$string['execute']                           = 'インポートを実行する';
$string['back']                              = '戻る';

// Preview
$string['previewtitle']                      = 'インポートプレビュー';
$string['action_create']                     = '登録';
$string['action_delete']                     = '削除';
$string['action_skip_duplicate']             = 'スキップ（既存あり）';
$string['action_skip_notfound']              = 'スキップ（対象なし）';
$string['col_row']                           = '行';
$string['col_action']                        = '操作';
$string['col_course']                        = 'コース';
$string['col_title']                         = 'タイトル';
$string['col_timestart']                     = '開始';
$string['col_timeend']                       = '終了';
$string['col_location']                      = '場所';
$string['col_description']                   = '説明';
$string['col_select']                        = '選択';
$string['warn_multihit']                     = '複数のイベントがヒットしました。削除するイベントを選択してください。';
$string['warn_notfound']                     = '対象イベントが見つかりませんでした。この行はスキップされます。';
$string['summary_create']                    = '登録予定：{$a} 件';
$string['summary_delete']                    = '削除予定：{$a} 件';
$string['summary_skip']                      = 'スキップ：{$a} 件';
$string['summary_warn']                      = '要確認：{$a} 行';

// Results
$string['resulttitle']                       = 'インポート完了';
$string['result_created']                    = '登録：{$a} 件';
$string['result_deleted']                    = '削除：{$a} 件';
$string['result_skipped']                    = 'スキップ：{$a} 件';
$string['result_errors']                     = 'エラー：{$a} 件';

// Errors
$string['error_invalidcsv']                  = '無効なCSVファイルです。';
$string['error_missingcolumn']               = '必須列が見つかりません：{$a}';
$string['error_invalidaction']               = '{$a->row} 行目：無効なaction "{$a->value}"。"create" または "delete" を指定してください。';
$string['error_invalidtimestart']            = '{$a->row} 行目：timestart の形式が正しくありません "{$a->value}"。YYYY-MM-DD HH:MM 形式で入力してください。';
$string['error_invalidtimeend']              = '{$a->row} 行目：timeend の形式が正しくありません "{$a->value}"。YYYY-MM-DD HH:MM 形式で入力してください。';
$string['error_timeendbeforestart']          = '{$a->row} 行目：timeend が timestart より前になっています。';
$string['error_invalidcourseid']             = '{$a->row} 行目：無効またはアクセス不可のコースID "{$a->value}"。';
$string['error_nocapability']                = 'コース {$a} へのイベント登録権限がありません。';
$string['error_noadminmode']                 = '複数コースへのインポート権限がありません。';
$string['error_missingcolumn_course']        = '管理者モードでは "courseid" または "courseidnumber" 列のどちらかが必要です。';
