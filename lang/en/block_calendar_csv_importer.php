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
 * English language strings for block_calendar_csv_importer.
 *
 * @package   block_calendar_csv_importer
 * @copyright 2026 Jichi Medical University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']                        = 'Calendar CSV Importer';
$string['calendar_csv_importer:import']      = 'Import CSV to course calendar';
$string['calendar_csv_importer:importany']   = 'Import CSV to any course calendar';

// Block UI
$string['openimporter']                      = 'Calendar CSV Importer';
$string['adminmode']                         = 'Admin mode (multi-course)';
$string['teachermode']                       = 'Teacher mode (this course)';

// Import page
$string['importtitle']                       = 'Calendar CSV Import';
$string['uploadcsv']                         = 'Upload CSV file';
$string['descformat']                        = 'Description format';
$string['descformat_plain']                  = 'Plain text';
$string['descformat_html']                   = 'HTML';
$string['descformat_markdown']               = 'Markdown (display not guaranteed)';
$string['preview']                           = 'Preview';
$string['execute']                           = 'Execute import';
$string['back']                              = 'Back';

// Preview
$string['previewtitle']                      = 'Import preview';
$string['action_create']                     = 'Create';
$string['action_delete']                     = 'Delete';
$string['action_skip_duplicate']             = 'Skip (already exists)';
$string['action_skip_notfound']              = 'Skip (not found)';
$string['col_row']                           = 'Row';
$string['col_action']                        = 'Action';
$string['col_course']                        = 'Course';
$string['col_title']                         = 'Title';
$string['col_timestart']                     = 'Start';
$string['col_timeend']                       = 'End';
$string['col_location']                      = 'Location';
$string['col_description']                   = 'Description';
$string['col_select']                        = 'Select';
$string['warn_multihit']                     = 'Multiple events found matching this row. Select the event(s) to delete.';
$string['warn_notfound']                     = 'No matching event found. This row will be skipped.';
$string['summary_create']                    = 'Create: {$a} event(s)';
$string['summary_delete']                    = 'Delete: {$a} event(s)';
$string['summary_skip']                      = 'Skip: {$a} event(s)';
$string['summary_warn']                      = 'Warnings: {$a} row(s) require attention';

// Results
$string['resulttitle']                       = 'Import complete';
$string['result_created']                    = 'Created: {$a}';
$string['result_deleted']                    = 'Deleted: {$a}';
$string['result_skipped']                    = 'Skipped: {$a}';
$string['result_errors']                     = 'Errors: {$a}';

// Errors
$string['error_invalidcsv']                  = 'Invalid CSV file.';
$string['error_missingcolumn']               = 'Required column missing: {$a}';
$string['error_invalidaction']               = 'Row {$a->row}: Invalid action "{$a->value}". Must be "create" or "delete".';
$string['error_invalidtimestart']            = 'Row {$a->row}: Invalid timestart format "{$a->value}". Use YYYY-MM-DD HH:MM.';
$string['error_invalidtimeend']              = 'Row {$a->row}: Invalid timeend format "{$a->value}". Use YYYY-MM-DD HH:MM.';
$string['error_timeendbeforestart']          = 'Row {$a->row}: timeend is before timestart.';
$string['error_invalidcourseid']             = 'Row {$a->row}: Invalid or inaccessible course ID "{$a->value}".';
$string['error_nocapability']                = 'You do not have permission to import events to course {$a}.';
$string['error_noadminmode']                 = 'You do not have permission to import to multiple courses.';
$string['error_missingcolumn_course']        = 'Either "courseid" or "courseidnumber" column is required in admin mode.';
