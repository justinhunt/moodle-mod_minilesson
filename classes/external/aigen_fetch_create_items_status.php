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

use context_module;
use context_system;
use core_external\external_multiple_structure;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_minilesson\constants;

/**
 * Class aigen_fetch_create_items_status
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_fetch_create_items_status extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([
            'jobids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Job id'), 'List of job ids'
            )
        ]);
    }

    public static function execute($jobids) {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), [
            'jobids' => $jobids,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        if (empty($params['jobids'])) {
            return ['jobs' => []];
        }

        $jobids = array_map('intval', $params['jobids']);
        $usages = $DB->get_records_list('minilesson_template_usages', 'id', $jobids);

        $jobs = [];
        foreach ($jobids as $jobid) {
            if (isset($usages[$jobid])) {
                $usage = $usages[$jobid];
                $progress = $usage->progress * 100;
                $status = !empty($usage->error) ? get_string('failed', constants::M_COMPONENT) :
                    get_string(($progress == 100) ? 'completed' : 'progress', constants::M_COMPONENT);

                $cm = get_coursemodule_from_instance(constants::M_MODNAME, $usage->minilessonid);
                if ($cm) {
                    $modulecontext = context_module::instance($cm->id);
                    if (!has_capability('mod/minilesson:canuseaigen', $modulecontext)) {
                        $status = get_string('failed', constants::M_COMPONENT);
                        $usage->error = get_string('notaccess', constants::M_COMPONENT);
                    }
                } else {
                    $status = get_string('notfound', constants::M_COMPONENT);
                    $usage->error = get_string('jobnotfound', constants::M_COMPONENT);
                }

                $jobs[] = [
                    'id' => $usage->id,
                    'lessonid' => $usage->minilessonid,
                    'status' => $status,
                    'message' => $usage->error,
                ];
            } else {
                $jobs[] = [
                    'id' => $jobid,
                    'lessonid' => 0,
                    'status' => get_string('notfound', constants::M_COMPONENT),
                    'message' => get_string('jobnotfound', constants::M_COMPONENT),
                ];
            }
        }

        return ['jobs' => $jobs];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'jobs' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Job ID'),
                    'lessonid' => new external_value(PARAM_INT, 'Lesson Id'),
                    'status' => new external_value(PARAM_TEXT, 'Job status'),
                    'message' => new external_value(PARAM_TEXT, 'Job message'),
                ]),
                'Jobs list'
            )
        ]);
    }
}
