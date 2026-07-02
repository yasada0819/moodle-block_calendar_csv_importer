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
 * CSV template download for block_calendar_csv_importer.
 *
 * @package   block_calendar_csv_importer
 * @copyright 2026 Jichi Medical University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();

$mode     = optional_param('mode', 'teacher', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);

$syscontext = context_system::instance();

if ($mode === 'admin') {
    require_capability('block/calendar_csv_importer:importany', $syscontext);
} else {
    if ($courseid <= 0) {
        throw new moodle_exception('invalidcourseid');
    }
    $course  = get_course($courseid);
    $context = context_course::instance($courseid);
    require_login($course);
    require_capability('block/calendar_csv_importer:import', $context);
}

// ---------------------------------------------------------------------------
// CSV 内容を組み立てる
// ---------------------------------------------------------------------------
$rows = [];

if ($mode === 'admin') {
    // 管理者モード：courseid / courseidnumber 列あり
    $rows[] = ['courseid', 'courseidnumber', 'action', 'title', 'timestart', 'timeend', 'location', 'description'];

    // サンプル行1：courseid指定・終了時刻あり
    $rows[] = [
        '7001',
        '',
        'create',
        '【1】データサイエンス総論',
        '2026-09-01 09:00',
        '2026-09-01 10:30',
        '第1講義室',
        '担当：山田太郎',
    ];

    // サンプル行2：courseidnumber指定・終了時刻なし
    $rows[] = [
        '',
        '2026_L1234',
        'create',
        'オリエンテーション',
        '2026-09-01 08:30',
        '',
        '',
        '全員参加',
    ];

    // サンプル行3：削除
    $rows[] = [
        '7001',
        '',
        'delete',
        '【1】データサイエンス総論 旧タイトル',
        '2026-08-25 09:00',
        '2026-08-25 10:30',
        '',
        '',
    ];

    $filename = 'calendar_csv_importer_template_admin.csv';

} else {
    // 教師モード：courseid列なし
    $rows[] = ['action', 'title', 'timestart', 'timeend', 'location', 'description'];

    // サンプル行1：登録・終了時刻あり
    $rows[] = [
        'create',
        '【1】データサイエンス総論',
        '2026-09-01 09:00',
        '2026-09-01 10:30',
        '第1講義室',
        '担当：山田太郎',
    ];

    // サンプル行2：登録・終了時刻なし
    $rows[] = [
        'create',
        'オリエンテーション',
        '2026-09-01 08:30',
        '',
        '',
        '全員参加',
    ];

    // サンプル行3：削除
    $rows[] = [
        'delete',
        '【1】データサイエンス総論 旧タイトル',
        '2026-08-25 09:00',
        '2026-08-25 10:30',
        '',
        '',
    ];

    $filename = 'calendar_csv_importer_template_teacher.csv';
}

// ---------------------------------------------------------------------------
// ダウンロード出力
// ---------------------------------------------------------------------------
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

// BOM付きUTF-8（Excelで文字化けしないように）
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
foreach ($rows as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;
