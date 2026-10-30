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

namespace minilessonitem_fiction\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use minilessonitem_fiction\itemtype;

/**
 * Checks a yarn script for the problems the import checks for.
 *
 * The Syntax Check button on the authoring form calls this, so that it reports the same
 * errors (missing jump targets, undeclared variables, ...) that would stop the story being
 * imported, and the same warnings (paths that end without <<stop>>) that the import gives.
 *
 * @package    minilessonitem_fiction
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_yarn extends external_api {
    /**
     * Describes the parameters for the check_yarn web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The minilesson module context id'),
            'yarn' => new external_value(PARAM_RAW, 'The yarn script'),
        ]);
    }

    /**
     * Checks a yarn script.
     *
     * @param int $contextid
     * @param string $yarn
     * @return array the errors and the warnings found
     */
    public static function execute($contextid, $yarn) {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['contextid' => $contextid, 'yarn' => $yarn]
        );

        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('mod/minilesson:itemedit', $context);

        return [
            'errors' => array_values(itemtype::check_yarn($params['yarn'])),
            'warnings' => itemtype::check_yarn_warnings($params['yarn']),
        ];
    }

    /**
     * Describes the return value of the check_yarn web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'errors' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'a problem that stops the story working')
            ),
            'warnings' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'something worth fixing that does not stop the story working')
            ),
        ]);
    }
}
