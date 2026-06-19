<?php
defined('MOODLE_INTERNAL') || die();

class block_calendar_csv_importer extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_calendar_csv_importer');
    }

    public function has_config() {
        return true;
    }

    public function applicable_formats() {
        return [
            'course-view' => true,
            'course'      => true,
        ];
    }

    public function get_content() {
        global $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         = new stdClass();
        $this->content->footer = '';
        $this->content->text   = '';

        // コースページ以外・サイトトップは対象外
        if ($COURSE->id == SITEID) {
            return $this->content;
        }

        $coursecontext = context_course::instance($COURSE->id);
        if (!has_capability('block/calendar_csv_importer:import', $coursecontext)) {
            return $this->content;
        }

        $teacherurl = new moodle_url('/blocks/calendar_csv_importer/import.php', [
            'mode'     => 'teacher',
            'courseid' => $COURSE->id,
        ]);
        $this->content->text = html_writer::tag('p',
            html_writer::link($teacherurl, get_string('openimporter', 'block_calendar_csv_importer'))
        );

        return $this->content;
    }
}
