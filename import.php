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
 * CSV import page for block_calendar_csv_importer.
 *
 * @package   block_calendar_csv_importer
 * @copyright 2026 Jichi Medical University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/calendar_csv_importer/locallib.php');

require_login();

// ---------------------------------------------------------------------------
// パラメータ取得
// ---------------------------------------------------------------------------
$mode     = optional_param('mode', 'teacher', PARAM_ALPHA);   // 'admin' or 'teacher'
$courseid = optional_param('courseid', 0, PARAM_INT);
$stage    = optional_param('stage', 'upload', PARAM_ALPHA);    // 'upload' / 'preview' / 'execute'

// ---------------------------------------------------------------------------
// 権限チェック
// ---------------------------------------------------------------------------
$syscontext = context_system::instance();

if ($mode === 'admin') {
    require_capability('block/calendar_csv_importer:importany', $syscontext);
    $context = $syscontext;
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/blocks/calendar_csv_importer/import.php', ['mode' => 'admin']));
} else {
    if ($courseid <= 0) {
        throw new moodle_exception('invalidcourseid');
    }
    $course = get_course($courseid);
    $context = context_course::instance($courseid);
    require_login($course);
    require_capability('block/calendar_csv_importer:import', $context);
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/blocks/calendar_csv_importer/import.php',
        ['mode' => 'teacher', 'courseid' => $courseid]));
}

$PAGE->set_title(get_string('importtitle', 'block_calendar_csv_importer'));
$PAGE->set_heading(get_string('importtitle', 'block_calendar_csv_importer'));
$PAGE->set_pagelayout('standard');

$adminmode = ($mode === 'admin');

// ---------------------------------------------------------------------------
// セッションキー確認用ヘルパー
// ---------------------------------------------------------------------------
function ccsvimport_sesskey_or_die() {
    if (!confirm_sesskey()) {
        throw new moodle_exception('invalidsesskey');
    }
}

// ---------------------------------------------------------------------------
// STAGE: execute（実行）
// ---------------------------------------------------------------------------
if ($stage === 'execute' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ccsvimport_sesskey_or_die();

    // セッションからrows・descformatを復元
    $sessionkey = 'ccsvimport_rows_' . sesskey();
    if (empty($_SESSION[$sessionkey])) {
        redirect(new moodle_url('/blocks/calendar_csv_importer/import.php',
            ['mode' => $mode, 'courseid' => $courseid]));
    }
    $rows       = json_decode($_SESSION[$sessionkey]['rows'], false);
    $descformat = $_SESSION[$sessionkey]['descformat'];
    unset($_SESSION[$sessionkey]);

    // 複数ヒット時の選択マップを取得
    // POSTキーは delete_rownum_eventid 形式
    $deletemap = [];
    foreach ($_POST as $k => $v) {
        $k = clean_param($k, PARAM_ALPHANUMEXT);
        if (strpos($k, 'delete_') === 0) {
            $inner = substr($k, strlen('delete_'));
            $deletemap[$inner] = 1;
        }
    }

    $result = ccsvimport_execute($rows, $descformat, $deletemap);

    // ---------------------------------------------------------------------------
    // 完了レポート表示
    // ---------------------------------------------------------------------------
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('resulttitle', 'block_calendar_csv_importer'));

    echo html_writer::tag('p', get_string('result_created', 'block_calendar_csv_importer', $result['created']));
    echo html_writer::tag('p', get_string('result_deleted', 'block_calendar_csv_importer', $result['deleted']));
    echo html_writer::tag('p', get_string('result_skipped', 'block_calendar_csv_importer', $result['skipped']));
    if (!empty($result['errors'])) {
        echo html_writer::tag('p', get_string('result_errors', 'block_calendar_csv_importer', count($result['errors'])));
        $errlist = html_writer::alist($result['errors']);
        echo html_writer::div($errlist, 'alert alert-warning');
    }

    $backurl = $adminmode
        ? new moodle_url('/blocks/calendar_csv_importer/import.php', ['mode' => 'admin'])
        : new moodle_url('/blocks/calendar_csv_importer/import.php', ['mode' => 'teacher', 'courseid' => $courseid]);
    echo html_writer::tag('p', html_writer::link($backurl, get_string('back', 'block_calendar_csv_importer')));

    echo $OUTPUT->footer();
    exit;
}

// ---------------------------------------------------------------------------
// STAGE: preview（プレビュー）
// ---------------------------------------------------------------------------
if ($stage === 'preview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ccsvimport_sesskey_or_die();

    $descformat = optional_param('descformat', CCSVIMPORT_FORMAT_PLAIN, PARAM_ALPHA);
    if (!in_array($descformat, [CCSVIMPORT_FORMAT_PLAIN, CCSVIMPORT_FORMAT_HTML, CCSVIMPORT_FORMAT_MARKDOWN])) {
        $descformat = CCSVIMPORT_FORMAT_PLAIN;
    }

    // ファイルアップロード確認
    if (empty($_FILES['csvfile']['tmp_name']) || !is_uploaded_file($_FILES['csvfile']['tmp_name'])) {
        redirect(new moodle_url('/blocks/calendar_csv_importer/import.php',
            ['mode' => $mode, 'courseid' => $courseid]),
            get_string('error_invalidcsv', 'block_calendar_csv_importer'));
    }
    $tmpfile = $_FILES['csvfile']['tmp_name'];

    // パース＆バリデーション
    $parsed = ccsvimport_parse_csv($tmpfile, $adminmode, $courseid);

    if (!empty($parsed['errors'])) {
        // バリデーションエラーはアップロード画面に戻す
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('importtitle', 'block_calendar_csv_importer'));
        echo html_writer::div(
            html_writer::tag('strong', 'エラー：') . html_writer::alist($parsed['errors']),
            'alert alert-danger'
        );
        ccsvimport_render_upload_form($mode, $courseid, $adminmode);
        echo $OUTPUT->footer();
        exit;
    }

    // コースIDの存在・権限チェック（管理者モード時）
    if ($adminmode) {
        $coursecheck_errors = [];
        foreach ($parsed['rows'] as $row) {
            try {
                $c = get_course($row->courseid);
                $ctx = context_course::instance($row->courseid);
                if (!has_capability('moodle/calendar:manageentries', $ctx)) {
                    $coursecheck_errors[] = get_string('error_nocapability', 'block_calendar_csv_importer', $row->courseid);
                }
            } catch (Exception $e) {
                $coursecheck_errors[] = get_string('error_invalidcourseid', 'block_calendar_csv_importer',
                    (object)['row' => $row->rownum, 'value' => $row->courseid]);
            }
        }
        if (!empty($coursecheck_errors)) {
            echo $OUTPUT->header();
            echo html_writer::div(
                html_writer::alist(array_unique($coursecheck_errors)),
                'alert alert-danger'
            );
            ccsvimport_render_upload_form($mode, $courseid, $adminmode);
            echo $OUTPUT->footer();
            exit;
        }
    }

    // プレビュー生成
    $rows = ccsvimport_build_preview($parsed['rows'], $descformat);

    // セッションに保存（execute時に復元）
    $sessionkey = 'ccsvimport_rows_' . sesskey();
    $_SESSION[$sessionkey] = [
        'rows'       => json_encode($rows),
        'descformat' => $descformat,
    ];

    // ---------------------------------------------------------------------------
    // プレビュー画面レンダリング
    // ---------------------------------------------------------------------------
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('previewtitle', 'block_calendar_csv_importer'));

    // サマリー
    $ncreate = 0; $ndelete = 0; $nskip = 0; $nwarn = 0;
    foreach ($rows as $row) {
        switch ($row->previewstatus) {
            case 'create':       $ncreate++; break;
            case 'delete':       $ndelete++; break;
            case 'warn_multihit': $nwarn++; break;
            default:             $nskip++;  break;
        }
    }
    $ndelete += $nwarn; // warn行も削除予定としてカウント（選択次第）

    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', get_string('summary_create', 'block_calendar_csv_importer', $ncreate));
    echo html_writer::tag('li', get_string('summary_delete', 'block_calendar_csv_importer', $ndelete));
    echo html_writer::tag('li', get_string('summary_skip',   'block_calendar_csv_importer', $nskip));
    if ($nwarn > 0) {
        echo html_writer::tag('li',
            html_writer::tag('strong', get_string('summary_warn', 'block_calendar_csv_importer', $nwarn)),
            ['class' => 'text-warning']);
    }
    echo html_writer::end_tag('ul');

    // プレビューテーブル
    $executeurl = new moodle_url('/blocks/calendar_csv_importer/import.php');

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $executeurl->out(false),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',  'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'stage',    'value' => 'execute']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'mode',     'value' => $mode]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);

    // テーブルヘッダー
    $hascourse = $adminmode;
    $thead = html_writer::tag('tr',
        html_writer::tag('th', get_string('col_row',       'block_calendar_csv_importer')) .
        html_writer::tag('th', get_string('col_action',    'block_calendar_csv_importer')) .
        ($hascourse ? html_writer::tag('th', get_string('col_course', 'block_calendar_csv_importer')) : '') .
        html_writer::tag('th', get_string('col_title',     'block_calendar_csv_importer')) .
        html_writer::tag('th', get_string('col_timestart', 'block_calendar_csv_importer')) .
        html_writer::tag('th', get_string('col_timeend',   'block_calendar_csv_importer')) .
        html_writer::tag('th', get_string('col_location',  'block_calendar_csv_importer')) .
        html_writer::tag('th', get_string('col_select',    'block_calendar_csv_importer'))
    );

    $tbody = '';
    foreach ($rows as $row) {
        $statuslabel = get_string('action_' . $row->previewstatus, 'block_calendar_csv_importer',
            $row->previewstatus);
        // action_warn_multihit は定義していないので delete として表示
        if ($row->previewstatus === 'warn_multihit') {
            $statuslabel = get_string('action_delete', 'block_calendar_csv_importer');
        }

        $rowclass = '';
        switch ($row->previewstatus) {
            case 'create':        $rowclass = 'table-success'; break;
            case 'delete':        $rowclass = 'table-danger';  break;
            case 'warn_multihit': $rowclass = 'table-warning'; break;
            default:              $rowclass = 'table-secondary'; break;
        }

        $timeendstr = $row->timeend ? userdate($row->timeend, get_string('strftimedatetimeshort', 'langconfig')) : '—';
        $timestarstr = userdate($row->timestart, get_string('strftimedatetimeshort', 'langconfig'));

        // 選択セル（複数ヒット時のみ表示）
        $selectcell = '';
        if ($row->previewstatus === 'warn_multihit') {
            $selectcell .= html_writer::tag('p',
                html_writer::tag('em', get_string('warn_multihit', 'block_calendar_csv_importer')),
                ['class' => 'text-warning small']);
            foreach ($row->hits as $hit) {
                $key   = 'delete_' . $row->rownum . '_' . $hit->id;
                $label = userdate($hit->timestart) . ' ' . s($hit->name);
                $selectcell .= html_writer::tag('label',
                    html_writer::empty_tag('input', [
                        'type'  => 'checkbox',
                        'name'  => $key,
                        'value' => 1,
                    ]) . ' ' . $label
                ) . html_writer::empty_tag('br');
            }
        } elseif ($row->previewstatus === 'skip_notfound') {
            $selectcell = html_writer::tag('em',
                get_string('warn_notfound', 'block_calendar_csv_importer'),
                ['class' => 'text-muted small']);
        }

        $tbody .= html_writer::tag('tr',
            html_writer::tag('td', $row->rownum) .
            html_writer::tag('td', s($statuslabel)) .
            ($hascourse ? html_writer::tag('td', $row->courseid) : '') .
            html_writer::tag('td', s($row->rawtitle)) .
            html_writer::tag('td', $timestarstr) .
            html_writer::tag('td', $timeendstr) .
            html_writer::tag('td', s($row->rawloc)) .
            html_writer::tag('td', $selectcell),
            ['class' => $rowclass]
        );
    }

    echo html_writer::tag('table',
        html_writer::tag('thead', $thead) . html_writer::tag('tbody', $tbody),
        ['class' => 'table table-sm table-bordered generaltable']
    );

    // 実行ボタン
    echo html_writer::tag('p',
        html_writer::empty_tag('input', [
            'type'  => 'submit',
            'value' => get_string('execute', 'block_calendar_csv_importer'),
            'class' => 'btn btn-primary',
        ])
    );
    echo html_writer::end_tag('form');

    // 戻るリンク
    $backurl = $adminmode
        ? new moodle_url('/blocks/calendar_csv_importer/import.php', ['mode' => 'admin'])
        : new moodle_url('/blocks/calendar_csv_importer/import.php', ['mode' => 'teacher', 'courseid' => $courseid]);
    echo html_writer::tag('p', html_writer::link($backurl, get_string('back', 'block_calendar_csv_importer')));

    echo $OUTPUT->footer();
    exit;
}

// ---------------------------------------------------------------------------
// STAGE: upload（初期画面）
// ---------------------------------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importtitle', 'block_calendar_csv_importer'));
ccsvimport_render_upload_form($mode, $courseid, $adminmode);
echo $OUTPUT->footer();

// ---------------------------------------------------------------------------
// アップロードフォームのレンダリング関数
// ---------------------------------------------------------------------------
function ccsvimport_render_upload_form(string $mode, int $courseid, bool $adminmode) {
    global $OUTPUT;

    $previewurl = new moodle_url('/blocks/calendar_csv_importer/import.php');

    echo html_writer::start_tag('form', [
        'method'  => 'post',
        'action'  => $previewurl->out(false),
        'enctype' => 'multipart/form-data',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',  'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'stage',    'value' => 'preview']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'mode',     'value' => $mode]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);

    // CSVファイル選択
    echo html_writer::tag('div',
        html_writer::tag('label',
            get_string('uploadcsv', 'block_calendar_csv_importer'),
            ['for' => 'csvfile', 'class' => 'col-form-label']
        ) .
        html_writer::empty_tag('input', [
            'type'   => 'file',
            'name'   => 'csvfile',
            'id'     => 'csvfile',
            'accept' => '.csv,text/csv',
            'class'  => 'form-control',
        ]),
        ['class' => 'mb-3']
    );

    // descriptionフォーマット選択
    $formats = [
        CCSVIMPORT_FORMAT_PLAIN    => get_string('descformat_plain',    'block_calendar_csv_importer'),
        CCSVIMPORT_FORMAT_HTML     => get_string('descformat_html',     'block_calendar_csv_importer'),
        CCSVIMPORT_FORMAT_MARKDOWN => get_string('descformat_markdown', 'block_calendar_csv_importer'),
    ];
    $radios = '';
    foreach ($formats as $val => $label) {
        $radios .= html_writer::tag('div',
            html_writer::tag('label',
                html_writer::empty_tag('input', [
                    'type'    => 'radio',
                    'name'    => 'descformat',
                    'value'   => $val,
                    'checked' => ($val === CCSVIMPORT_FORMAT_PLAIN) ? 'checked' : null,
                    'class'   => 'form-check-input',
                ]) . ' ' . $label,
                ['class' => 'form-check-label']
            ),
            ['class' => 'form-check']
        );
    }
    echo html_writer::tag('div',
        html_writer::tag('label',
            get_string('descformat', 'block_calendar_csv_importer'),
            ['class' => 'col-form-label']
        ) . $radios,
        ['class' => 'mb-3']
    );

    // CSVフォーマット説明
    if ($adminmode) {
        $csvcolumns = 'courseid, courseidnumber, action, title, timestart, [timeend], [location], [description]';
        $coursenote = 'courseid（course.id の数値）または courseidnumber（course.idnumber の文字列）のどちらかを指定。両方入力した場合は courseid 優先。';
    } else {
        $csvcolumns = 'action, title, timestart, [timeend], [location], [description]';
        $coursenote = '';
    }
    echo html_writer::tag('div',
        html_writer::tag('small',
            html_writer::tag('code', $csvcolumns) .
            html_writer::empty_tag('br') .
            'action: create / delete　　timestart/timeend: YYYY-MM-DD HH:MM　　[ ] は任意列' .
            ($coursenote !== '' ? html_writer::empty_tag('br') . $coursenote : ''),
            ['class' => 'text-muted']
        ),
        ['class' => 'mb-3']
    );

    // 送信ボタン
    echo html_writer::tag('div',
        html_writer::empty_tag('input', [
            'type'  => 'submit',
            'value' => get_string('preview', 'block_calendar_csv_importer'),
            'class' => 'btn btn-primary',
        ]),
        ['class' => 'mb-3']
    );

    echo html_writer::end_tag('form');

    // テンプレートダウンロードリンク（フォームの外）
    $templateurl = new moodle_url('/blocks/calendar_csv_importer/download_template.php', [
        'mode'     => $mode,
        'courseid' => $courseid,
    ]);
    echo html_writer::tag('p',
        html_writer::link($templateurl, get_string('downloadtemplate', 'block_calendar_csv_importer'),
            ['class' => 'text-muted small'])
    );
}
