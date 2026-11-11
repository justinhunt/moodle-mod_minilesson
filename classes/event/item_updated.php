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
 * An item of a lesson was changed through the aigen web service (an AI client, or the lesson assistant).
 *
 * @property-read array $other {
 *      - string itemname: the item's name
 *      - string itemtype: the item's type
 *      - string[] fields: the import names of the fields that changed
 * }
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_updated extends \core\event\base {
    /**
     * Create the event for an item that has just been saved.
     *
     * @param \stdClass $item the item's record, as saved
     * @param \context_module $modulecontext the lesson's context
     * @param string[] $fields the import names of the fields that changed
     * @return item_updated
     */
    public static function create_from_item(\stdClass $item, \context_module $modulecontext, array $fields) {
        $event = self::create([
            'context' => $modulecontext,
            'objectid' => $item->id,
            'other' => [
                'itemname' => (string) $item->name,
                'itemtype' => (string) $item->type,
                'fields' => array_values($fields),
            ],
        ]);
        $event->add_record_snapshot(constants::M_QTABLE, $item);
        return $event;
    }

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = constants::M_QTABLE;
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' changed the fields '" . implode(', ', $this->other['fields'])
            . "' of the " . $this->other['itemtype'] . " item with id '$this->objectid' in the minilesson with "
            . "course module id '$this->contextinstanceid', through the web service.";
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventitemupdated', constants::M_COMPONENT);
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
     * How the item is found again after a restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => constants::M_QTABLE, 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Nothing in "other" needs mapping after a restore.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
