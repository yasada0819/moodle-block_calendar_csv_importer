<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Core logic for block_calendar_csv_importer.
 *
 * @package   block_calendar_csv_importer
 * @copyright 2026 Jichi Medical University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

// アクション定数
define('CCSVIMPORT_ACTION_CREATE', 'create');
define('CCSVIMPORT_ACTION_DELETE', 'delete');

// descriptionフォーマット定数（インポート画面のラジオボタン値）
define('CCSVIMPORT_FORMAT_PLAIN',    'plain');
define('CCSVIMPORT_FORMAT_HTML',     'html');
define('CCSVIMPORT_FORMAT_MARKDOWN', 'markdown');

/**
 * フォーマット文字列をMoodle FORMAT_* 定数に変換する
 *
 * @param string $format
 * @return int
 */
function ccsvimport_format_to_moodle(string $format): int {
    switch ($format) {
        case CCSVIMPORT_FORMAT_HTML:     return FORMAT_HTML;
        case CCSVIMPORT_FORMAT_MARKDOWN: return FORMAT_MARKDOWN;
        default:                         return FORMAT_PLAIN;
    }
}

/**
 * CSVファイルをパースしてバリデーションを行い、行データの配列を返す。
 *
 * @param string $filepath  一時ファイルパス
 * @param bool   $adminmode trueなら courseid 列を必須とする
 * @param int    $fixedcourseid 教師モード時のコースID
 * @return array ['rows' => [...], 'errors' => [...]]
 *   rows の各要素: stdClass {rownum, action, courseid, title, timestart, timeend, location, description}
 */
function ccsvimport_parse_csv(string $filepath, bool $adminmode, int $fixedcourseid = 0): array {
    $errors = [];
    $rows   = [];

    // BOM除去のため一時ファイルに書き直してからfgetcsvで読む
    // （fgetcsvはクォート内改行を正しく扱える）
    $content = file_get_contents($filepath);
    if ($content === false) {
        return ['rows' => [], 'errors' => [get_string('error_invalidcsv', 'block_calendar_csv_importer')]];
    }
    // UTF-8 BOM除去
    $content = ltrim($content, "\xEF\xBB\xBF");

    // 一時ファイルに書き直してfgetcsvで読み込む
    $tmpfile = tempnam(sys_get_temp_dir(), 'ccsvimport_');
    file_put_contents($tmpfile, $content);
    $fh = fopen($tmpfile, 'r');
    if (!$fh) {
        @unlink($tmpfile);
        return ['rows' => [], 'errors' => [get_string('error_invalidcsv', 'block_calendar_csv_importer')]];
    }

    // ヘッダー行を取得
    $header = fgetcsv($fh);
    if (!$header) {
        fclose($fh);
        @unlink($tmpfile);
        return ['rows' => [], 'errors' => [get_string('error_invalidcsv', 'block_calendar_csv_importer')]];
    }
    $header = array_map('trim', $header);
    $colmap = array_flip($header);

    // 必須列チェック
    $required = ['action', 'title', 'timestart'];
    foreach ($required as $col) {
        if (!isset($colmap[$col])) {
            $errors[] = get_string('error_missingcolumn', 'block_calendar_csv_importer', $col);
        }
    }
    // 管理者モード: courseid / courseidnumber のどちらかが列として存在すればOK
    if ($adminmode) {
        $has_courseid       = isset($colmap['courseid']);
        $has_courseidnumber = isset($colmap['courseidnumber']);
        if (!$has_courseid && !$has_courseidnumber) {
            $errors[] = get_string('error_missingcolumn_course', 'block_calendar_csv_importer');
        }
    }
    if (!empty($errors)) {
        fclose($fh);
        @unlink($tmpfile);
        return ['rows' => [], 'errors' => $errors];
    }

    // 各行をパース（fgetcsvはクォート内改行を透過的に処理する）
    $rownum = 1;
    while (($cells = fgetcsv($fh)) !== false) {
        if ($cells === [null]) {
            continue; // 空行スキップ
        }
        $rownum++;

        $get = function(string $col) use ($cells, $colmap): string {
            return isset($colmap[$col]) && isset($cells[$colmap[$col]])
                ? trim($cells[$colmap[$col]])
                : '';
        };

        $row            = new stdClass();
        $row->rownum    = $rownum;
        $row->rawtitle  = $get('title');
        $row->rawloc    = $get('location');
        $row->rawdesc   = $get('description');

        // action
        $actionraw = strtolower($get('action'));
        if ($actionraw === '') {
            $actionraw = CCSVIMPORT_ACTION_CREATE;
        }
        if (!in_array($actionraw, [CCSVIMPORT_ACTION_CREATE, CCSVIMPORT_ACTION_DELETE])) {
            $errors[] = get_string('error_invalidaction', 'block_calendar_csv_importer',
                (object)['row' => $rownum, 'value' => $actionraw]);
            continue;
        }
        $row->action = $actionraw;

        // courseid解決
        // courseid（数値）優先、なければ courseidnumber（idnumber）で検索
        if ($adminmode) {
            $courseidraw       = $get('courseid');
            $courseidnumberraw = $get('courseidnumber');

            if ($courseidraw !== '') {
                // courseid列に値あり → course.id として解決
                $resolved = ccsvimport_resolve_by_id($courseidraw);
                $usedval  = $courseidraw;
            } elseif ($courseidnumberraw !== '') {
                // courseidnumber列に値あり → course.idnumber として解決
                $resolved = ccsvimport_resolve_by_idnumber($courseidnumberraw);
                $usedval  = $courseidnumberraw;
            } else {
                $resolved = false;
                $usedval  = '';
            }

            if ($resolved === false) {
                $errors[] = get_string('error_invalidcourseid', 'block_calendar_csv_importer',
                    (object)['row' => $rownum, 'value' => $usedval]);
                continue;
            }
            $row->courseid = $resolved;
        } else {
            $row->courseid = $fixedcourseid;
        }

        // timestart
        $timestartraw = $get('timestart');
        $timestart = ccsvimport_parse_datetime($timestartraw);
        if ($timestart === false) {
            $errors[] = get_string('error_invalidtimestart', 'block_calendar_csv_importer',
                (object)['row' => $rownum, 'value' => $timestartraw]);
            continue;
        }
        $row->timestart = $timestart;

        // timeend（任意）
        $timeendraw = $get('timeend');
        if ($timeendraw !== '') {
            $timeend = ccsvimport_parse_datetime($timeendraw);
            if ($timeend === false) {
                $errors[] = get_string('error_invalidtimeend', 'block_calendar_csv_importer',
                    (object)['row' => $rownum, 'value' => $timeendraw]);
                continue;
            }
            if ($timeend <= $timestart) {
                $errors[] = get_string('error_timeendbeforestart', 'block_calendar_csv_importer',
                    (object)['row' => $rownum]);
                continue;
            }
            $row->timeduration = $timeend - $timestart;
            $row->timeend      = $timeend;
        } else {
            $row->timeduration = 0;
            $row->timeend      = null;
        }

        $rows[] = $row;
    }

    fclose($fh);
    @unlink($tmpfile);
    return ['rows' => $rows, 'errors' => $errors];
}

/**
 * 日時文字列をUnixタイムスタンプに変換する。
 *
 * 対応フォーマット:
 *   YYYY-MM-DD HH:MM  （例: 2026-09-01 09:00）
 *   YYYY/MM/DD HH:MM  （例: 2026/09/01 09:00）
 *   YYYY/M/D H:MM     （例: 2026/9/1 9:00 ← Excelのスラッシュ区切り）
 *   YYYY-MM-DD        （例: 2026-09-01 ← 時刻省略時は 00:00 として扱う）
 *   YYYY/MM/DD        （例: 2026/09/01 ← 時刻省略時は 00:00 として扱う）
 *
 * @param string $str
 * @return int|false
 */
function ccsvimport_parse_datetime(string $str) {
    $str = trim($str);
    if ($str === '') {
        return false;
    }

    // スラッシュ区切りをハイフンに統一し、月日を0埋め
    // 例: 2026/9/1 9:00 → 2026-09-01 9:00
    $str = preg_replace_callback(
        '/^(\d{4})\/(\d{1,2})\/(\d{1,2})(.*)$/',
        function($m) {
            return sprintf('%04d-%02d-%02d%s', $m[1], $m[2], $m[3], $m[4]);
        },
        $str
    );

    // 時刻なし（日付のみ）の場合は 00:00 を補完
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
        $str .= ' 00:00';
    }

    // DateTime::createFromFormat で厳密にパース（strtotime より安全）
    $dt = DateTime::createFromFormat('Y-m-d H:i', $str);
    if (!$dt) {
        return false;
    }
    $errors = DateTime::getLastErrors();
    if ($errors['warning_count'] > 0 || $errors['error_count'] > 0) {
        return false;
    }
    return $dt->getTimestamp();
}

/**
 * パース済み行データからプレビュー情報を生成する。
 *
 * 各行に以下のプロパティを追加して返す:
 *   - previewstatus: 'create' / 'delete' / 'skip_duplicate' / 'skip_notfound' / 'warn_multihit'
 *   - hits: 削除時にヒットした既存イベントの配列（複数ヒット時に使用）
 *
 * @param array  $rows       ccsvimport_parse_csv() が返す rows
 * @param string $descformat CCSVIMPORT_FORMAT_* 定数
 * @return array
 */
function ccsvimport_build_preview(array $rows, string $descformat): array {
    global $DB;

    foreach ($rows as $row) {
        if ($row->action === CCSVIMPORT_ACTION_CREATE) {
            // 重複チェック
            $existing = ccsvimport_find_events($row->courseid, $row->timestart, $row->timeduration);
            $row->previewstatus = empty($existing) ? 'create' : 'skip_duplicate';
            $row->hits          = $existing;

        } else {
            // 削除対象検索
            $existing = ccsvimport_find_events($row->courseid, $row->timestart, $row->timeduration);
            if (empty($existing)) {
                $row->previewstatus = 'skip_notfound';
                $row->hits          = [];
            } elseif (count($existing) === 1) {
                $row->previewstatus = 'delete';
                $row->hits          = $existing;
            } else {
                $row->previewstatus = 'warn_multihit';
                $row->hits          = $existing;
            }
        }
    }

    return $rows;
}

/**
 * courseid + timestart + timeduration でカレンダーイベントを検索する。
 *
 * @param int $courseid
 * @param int $timestart  Unixタイムスタンプ
 * @param int $timeduration 秒
 * @return array mdl_event レコードの配列
 */
function ccsvimport_find_events(int $courseid, int $timestart, int $timeduration): array {
    global $DB;
    return $DB->get_records('event', [
        'courseid'     => $courseid,
        'timestart'    => $timestart,
        'timeduration' => $timeduration,
        'eventtype'    => 'course',
    ]);
}

/**
 * プレビュー確認後に実際のインポートを実行する。
 *
 * @param array  $rows       ccsvimport_build_preview() が返す rows
 * @param string $descformat CCSVIMPORT_FORMAT_* 定数
 * @param array  $deletemap  複数ヒット時にユーザーが選択したイベントID
 *                           ['rownum_eventid' => '1'] の形式
 * @return array ['created' => N, 'deleted' => N, 'skipped' => N, 'errors' => [...]]
 */
function ccsvimport_execute(array $rows, string $descformat, array $deletemap): array {
    $result = ['created' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => []];
    $moodleformat = ccsvimport_format_to_moodle($descformat);

    foreach ($rows as $row) {
        try {
            if ($row->previewstatus === 'create') {
                // 登録
                $eventdata                = new stdClass();
                $eventdata->eventtype     = 'course';
                $eventdata->type          = CALENDAR_EVENT_TYPE_STANDARD;
                $eventdata->name          = $row->rawtitle;
                $eventdata->description   = $row->rawdesc;
                $eventdata->format        = $moodleformat;
                $eventdata->location      = $row->rawloc;
                $eventdata->courseid      = $row->courseid;
                $eventdata->groupid       = 0;
                $eventdata->userid        = 0;
                $eventdata->timestart     = $row->timestart;
                $eventdata->timeduration  = $row->timeduration;
                $eventdata->visible       = 1;
                calendar_event::create($eventdata, false);
                $result['created']++;

            } elseif ($row->previewstatus === 'delete') {
                // 単一ヒット → そのまま削除
                $event = calendar_event::load(reset($row->hits)->id);
                $event->delete(false);
                $result['deleted']++;

            } elseif ($row->previewstatus === 'warn_multihit') {
                // 複数ヒット → ユーザーが選択したIDのみ削除
                $deleted = false;
                foreach ($row->hits as $hit) {
                    $key = $row->rownum . '_' . $hit->id;
                    if (!empty($deletemap[$key])) {
                        $event = calendar_event::load($hit->id);
                        $event->delete(false);
                        $result['deleted']++;
                        $deleted = true;
                    }
                }
                if (!$deleted) {
                    $result['skipped']++;
                }

            } else {
                // skip_duplicate / skip_notfound
                $result['skipped']++;
            }

        } catch (Exception $e) {
            $result['errors'][] = "Row {$row->rownum}: " . $e->getMessage();
        }
    }

    return $result;
}

/**
 * course.id（数値）で検索して内部IDを返す。
 *
 * @param string $value
 * @return int|false
 */
function ccsvimport_resolve_by_id(string $value) {
    global $DB;
    $value = trim($value);
    if (!is_numeric($value) || (int)$value <= 0) {
        return false;
    }
    return $DB->record_exists('course', ['id' => (int)$value]) ? (int)$value : false;
}

/**
 * course.idnumber で検索して内部IDを返す。
 *
 * @param string $value
 * @return int|false
 */
function ccsvimport_resolve_by_idnumber(string $value) {
    global $DB;
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    $course = $DB->get_record('course', ['idnumber' => $value], 'id', IGNORE_MISSING);
    return $course ? (int)$course->id : false;
}
