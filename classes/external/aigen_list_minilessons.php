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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use mod_minilesson\constants;

/**
 * Class aigen_list_minilessons
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_list_minilessons extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    public static function execute($courseid) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        require_capability('mod/minilesson:addinstance', $context);

        $modinfo = get_fast_modinfo($params['courseid']);
        $minilessons = [];
        foreach ($modinfo->get_instances_of(constants::M_MODNAME) as $cm) {
            if ($cm->deletioninprogress || !$cm->uservisible) {
                continue;
            }

            $minilessons[] = [
                'id' => $cm->id,
                'name' => $cm->name,
            ];
        }

        return $minilessons;
    }

    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, "Minilesson ID"),
                'name' => new external_value(PARAM_TEXT, "Minilesson Name"),
            ])
        );
    }
}
