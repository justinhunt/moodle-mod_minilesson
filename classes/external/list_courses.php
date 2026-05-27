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

use core_enrol_external;
use core_external\external_function_parameters;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/externallib.php');

/**
 * Class list_courses
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_courses extends core_enrol_external {

    /**
     * Returns description of method parameters.
     *
     * userid is intentionally dropped from the public parameter list: callers
     * may only list their own courses, never another user's.
     *
     * @return external_function_parameters
     */
    public static function get_users_courses_parameters() {
        $payloadstructure = parent::get_users_courses_parameters();
        unset($payloadstructure->keys['userid']);
        return $payloadstructure;
    }

    /**
     * List the authenticated caller's courses. The $userid argument is kept for
     * signature compatibility with the parent but is intentionally ignored — we
     * always operate on the authenticated user to avoid leaking other users'
     * enrolments.
     *
     * @param int $userid ignored
     * @param bool $returnusercount
     * @return array of courses
     */
    public static function get_users_courses($userid = 0, $returnusercount = true) {
        global $USER;
        return parent::get_users_courses($USER->id, $returnusercount);
    }
}
