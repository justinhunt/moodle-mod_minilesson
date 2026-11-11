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
use mod_minilesson\local\aigen\lessonsettings;

/**
 * Changes settings of a minilesson, from a short list.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_update_lesson_settings extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course Module ID of the minilesson'),
            'settingsjson' => new external_value(
                PARAM_RAW,
                'A JSON object of the settings to change, by the names aigen_get_lesson_settings gives: '
                . '{"showqtitles": "0", "name": "Unit 3 practice"}. Settings left out keep their values.'
            ),
            'dryrun' => new external_value(
                PARAM_BOOL,
                'When true, nothing is saved: the settings are checked and the changes they would make are returned.',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Change settings.
     *
     * @param int $cmid the course module id
     * @param string $settingsjson the settings
     * @param bool $dryrun check only
     * @return array
     */
    public static function execute($cmid, $settingsjson, $dryrun = false) {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'settingsjson' => $settingsjson,
            'dryrun' => $dryrun,
        ]);
        $cm = get_coursemodule_from_id(constants::M_MODNAME, $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);

        $changes = json_decode($params['settingsjson'], true);
        if (!is_array($changes) || (array_is_list($changes) && $changes)) {
            return [
                'success' => false,
                'status' => 'invalid',
                'message' => get_string('edit:badsettings', constants::M_COMPONENT),
                'changes' => [],
            ];
        }
        $result = lessonsettings::apply($cm, $moduleinstance, $changes, !empty($params['dryrun']));
        return [
            'success' => $result->status !== 'invalid',
            'status' => $result->status,
            'message' => $result->message,
            'changes' => array_map(function ($change) {
                return (array) $change;
            }, $result->changes),
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True unless the settings were refused'),
            'status' => new external_value(
                PARAM_ALPHA,
                '"updated", "wouldupdate" (dry run), "unchanged", or "invalid" (nothing was saved: see message)'
            ),
            'message' => new external_value(PARAM_RAW, 'Why the settings were refused'),
            'changes' => new external_multiple_structure(
                new external_single_structure([
                    'setting' => new external_value(PARAM_ALPHANUMEXT, 'The setting'),
                    'before' => new external_value(PARAM_RAW, 'Its value before'),
                    'after' => new external_value(PARAM_RAW, 'Its value after'),
                ]),
                'The settings that changed (or would)'
            ),
        ]);
    }
}
