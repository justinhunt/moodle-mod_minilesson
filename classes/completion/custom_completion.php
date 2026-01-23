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

declare(strict_types=1);

namespace mod_minilesson\completion;

use core_completion\activity_custom_completion;
use mod_minilesson\constants;
use mod_minilesson\utils;

/**
 * Activity custom completion subclass for the lesson activity.
 *
 * Contains the class for defining modn's custom completion rules
 * and fetching a lesson instance's completion statuses for a user.
 *
 * @package mod_minilesson
 * @copyright Justin Hunt <justin@poodll.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion
{
    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int
    {
        global $DB, $USER;

        $this->validate_rule($rule);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $this->cm->instance]);
        $status = utils::is_complete($rule, $moduleinstance, $this->userid);
        return $status ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array
    {
        return [
            'completionwhenfinished',
            'mingrade',
        ];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array
    {
        global $DB;
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $this->cm->instance]);
        $mingrade = $moduleinstance->mingrade;

        return [
            'completionwhenfinished' => get_string('completiondetail:finished', constants::M_COMPONENT),
            'mingrade' => get_string('completiondetail:mingrade', constants::M_COMPONENT, $mingrade),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array
    {
        return [
            'completionview',
            'completionwhenfinished',
            'completionusegrade',
            'completionpassgrade',
            'mingrade',
        ];
    }
}
