<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('blocksettings', new admin_externalpage(
        'block_calendar_csv_importer_import',
        get_string('openimporter', 'block_calendar_csv_importer'),
        new moodle_url('/blocks/calendar_csv_importer/import.php', ['mode' => 'admin']),
        'block/calendar_csv_importer:importany'
    ));

    // デフォルトの設定ページを無効化（外部ページのみを使用するため）
    $settings = null;
}
