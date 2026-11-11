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

namespace mod_minilesson\external;

use context_course;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_minilesson\constants;

/**
 * Lists a course's sections in order, with the activities in each and, for each minilesson, its item count.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_get_course_outline extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * The course outline.
     *
     * @param int $courseid the course
     * @return array
     */
    public static function execute($courseid) {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('mod/minilesson:addinstance', $context);

        $modinfo = get_fast_modinfo($params['courseid']);
        $course = $modinfo->get_course();
        $counts = $DB->get_records_sql_menu(
            'SELECT q.minilesson, COUNT(1) FROM {' . constants::M_QTABLE . '} q
               JOIN {' . constants::M_TABLE . '} m ON m.id = q.minilesson
              WHERE m.course = ?
           GROUP BY q.minilesson',
            [$params['courseid']]
        );
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $activities = [];
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if ($cm->deletioninprogress || !$cm->uservisible) {
                    continue;
                }
                $isminilesson = $cm->modname === constants::M_MODNAME;
                $activities[] = [
                    'cmid' => (int) $cm->id,
                    'modname' => $cm->modname,
                    'name' => $cm->name,
                    'visible' => (bool) $cm->visible,
                    'itemcount' => $isminilesson ? (int) ($counts[$cm->instance] ?? 0) : 0,
                ];
            }
            $sections[] = [
                'section' => (int) $section->section,
                'name' => get_section_name($course, $section),
                'visible' => (bool) $section->visible,
                'activities' => $activities,
            ];
        }
        return [
            'courseid' => (int) $course->id,
            'fullname' => format_string($course->fullname),
            'sections' => $sections,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'fullname' => new external_value(PARAM_RAW, 'The course name'),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'section' => new external_value(PARAM_INT, 'Section number, in course order'),
                    'name' => new external_value(PARAM_RAW, 'Section name'),
                    'visible' => new external_value(PARAM_BOOL, 'True if learners can see the section'),
                    'activities' => new external_multiple_structure(
                        new external_single_structure([
                            'cmid' => new external_value(PARAM_INT, 'Course Module ID (the cmid the other tools take)'),
                            'modname' => new external_value(PARAM_RAW, 'Activity type: minilesson, quiz, page ...'),
                            'name' => new external_value(PARAM_RAW, 'Activity name'),
                            'visible' => new external_value(PARAM_BOOL, 'True if learners can see it'),
                            'itemcount' => new external_value(PARAM_INT, 'For a minilesson, how many items it has'),
                        ]),
                        'The section\'s activities in course order'
                    ),
                ])
            ),
        ]);
    }
}
