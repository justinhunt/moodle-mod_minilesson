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
 * The mod_minilesson attempt submitted event.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_minilesson\event;

defined('MOODLE_INTERNAL') || die();

use \mod_minilesson\constants;

/**
 * The mod_minilesson stepsubmitted event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - bool submission_editable: is submission editable.
 * }
 *
 * @package    mod_minilesson
 * @since      Moodle 2.6
 * @copyright  2023 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step_submitted extends \core\event\base {

    /**
     * Create instance of event.
     *
     * @since Moodle 2.7
     *
     * @param $minilesson
     * @param $submission
     * @param $editable
     * @return attempt_submitted
     */
    public static function create_from_attempt($attempt,$modulecontext,$stepindex) {
        global $USER;

        $data = array(
            'context' => $modulecontext,
            'objectid' => $attempt->id,
            'userid' => $USER->id,
            'relateduserid' => $USER->id,
            'other' => ['submitterid'=>$USER->id,'stepindex'=>$stepindex]
        );
        if (!empty($attempt->userid) && ($attempt->userid != $USER->id)) {
            $data['relateduserid'] = $attempt->userid;
        }
        /** @var attempt_submitted $event */
        $event =  self::create($data);
        $event->add_record_snapshot(constants::M_ATTEMPTSTABLE, $attempt);
        return $event;
    }

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = constants::M_ATTEMPTSTABLE;
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->relateduserid' has submitted step '".$this->other['stepindex']."' the attempt with id '$this->objectid' for the " .
            "minilesson with course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventminilessonstepsubmitted', constants::M_COMPONENT);
    }


    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/minilesson/reports.php',
            array('report'=>'attemptresults','attemptid' => $this->objectid, 'id'=>$this->contextinstanceid));
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!array_key_exists('submitterid', $this->other)) {
            throw new \coding_exception('The \'submitterid\' value must be set in other.');
        }

        if (!array_key_exists('stepindex', $this->other)) {
            throw new \coding_exception('The \'stepindex\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => constants::M_ATTEMPTSTABLE, 'restore' => 'attempt');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['submitterid'] = array('db' => 'user', 'restore' => 'user');
        $othermapped['moduleid'] = array('db' => constants::M_TABLE, 'restore' => constants::M_MODNAME);

        return $othermapped;
    }


}
