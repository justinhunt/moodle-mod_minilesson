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

namespace mod_minilesson\event;

use mod_minilesson\constants;

/**
 * The items of a lesson were put in a new order through the aigen web service.
 *
 * @property-read array $other {
 *      - int[] itemids: the lesson's item ids in the new order
 * }
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class items_reordered extends \core\event\base {
    /**
     * Create the event for a lesson.
     *
     * @param \context_module $modulecontext the lesson's context
     * @param int $minilessonid the lesson's instance id
     * @param int[] $itemids the item ids in the new order
     * @return items_reordered
     */
    public static function create_from_lesson(\context_module $modulecontext, int $minilessonid, array $itemids) {
        return self::create([
            'context' => $modulecontext,
            'objectid' => $minilessonid,
            'other' => ['itemids' => array_values(array_map('intval', $itemids))],
        ]);
    }

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = constants::M_TABLE;
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' put the items of the minilesson with course module id "
            . "'$this->contextinstanceid' in a new order, through the web service.";
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventitemsreordered', constants::M_COMPONENT);
    }

    /**
     * The lesson's items page.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/minilesson/rsquestion/rsquestions.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * How the lesson is found again after a restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => constants::M_TABLE, 'restore' => 'minilesson'];
    }

    /**
     * The item ids in "other" are not mapped after a restore.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
