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
 * This is a one-line short description of the file
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_minilesson
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace minilesson with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

$id = required_param('id', PARAM_INT);   // Course.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$coursecontext = context_course::instance($course->id);

$PAGE->set_url('/mod/minilesson/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($coursecontext);

echo $OUTPUT->header();

if (! $minilessons = get_all_instances_in_course('minilesson', $course)) {
    notice(get_string('nominilessons', 'minilesson'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
if ($course->format == 'weeks') {
    $table->head  = [get_string('week'), get_string('name')];
    $table->align = ['center', 'left'];
} elseif ($course->format == 'topics') {
    $table->head  = [get_string('topic'), get_string('name')];
    $table->align = ['center', 'left', 'left', 'left'];
} else {
    $table->head  = [get_string('name')];
    $table->align = ['left', 'left', 'left'];
}

foreach ($minilessons as $minilesson) {
    if (!$minilesson->visible) {
        $link = html_writer::link(
            new moodle_url(
                '/mod/minilesson/view.php',
                ['id' => $minilesson->coursemodule]
            ),
            format_string($minilesson->name, true),
            ['class' => 'dimmed']
        );
    } else {
        $link = html_writer::link(
            new moodle_url(
                '/mod/minilesson/view.php',
                ['id' => $minilesson->coursemodule]
            ),
            format_string($minilesson->name, true)
        );
    }

    if ($course->format == 'weeks' || $course->format == 'topics') {
        $table->data[] = [$minilesson->section, $link];
    } else {
        $table->data[] = [$link];
    }
}

echo $OUTPUT->heading(get_string('modulenameplural', 'minilesson'), 2);
echo html_writer::table($table);
echo $OUTPUT->footer();
