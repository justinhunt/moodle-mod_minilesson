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

namespace mod_minilesson;

use html_writer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Class attempt_continue_form
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_continue_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;
        $this->set_display_vertical();

        $mform->addElement('hidden', 'attemptid');
        $mform->setType('attemptid', PARAM_INT);
    }

    public function definition_after_data() {
        global $DB, $PAGE;
        $mform = $this->_form;
        $attemptid = $this->get_element_value('attemptid');

        $html = html_writer::start_div('restore_lesson text-center py-3 mt-5');

        $mform->addElement('html', $html);

        $mform->addElement('html', '<h3 class="restore_lesson_header">' . get_string('continuepreviousattempt', constants::M_COMPONENT) . '</h3>');

        $lessoncountmessage = '';
        if (!empty($attemptid)) {
            $attemptrecord = $DB->get_record(constants::M_ATTEMPTSTABLE, ['id' => $attemptid]);
            if (!empty($attemptrecord)) {
                $totallessonitems = $DB->count_records(constants::M_QTABLE, ['minilesson' => $attemptrecord->moduleid]);
                $completedlessonitems = 0;
                if ($attemptrecord->sessiondata) {
                    $completedlessonitems = count(json_decode($attemptrecord->sessiondata)->steps);
                }
                $count = [
                    'completed' => $completedlessonitems,
                    'totallessonitem' => $totallessonitems,
                ];
                $lessoncountmessage = get_string('attemptquestioncountmessage', constants::M_COMPONENT, $count) . '</br>';
            }
        }

        $mform->addElement('html', '<p class="restore_lesson_text"> '. $lessoncountmessage .
            get_string('attemptreusequestion', constants::M_COMPONENT) . '</p>');

        $deletebtn = $mform->createElement('submit', 'delete', get_string('no'));
        $deletebtn->_generateId();
        $buttons[] = $deletebtn;

        $buttons[] = $mform->createElement('submit', 'continue', get_string('yes'));
        $group = $mform->addGroup($buttons, 'continueformbuttons', null, null, false, ['class' => 'ml-continueformbuttons']);
        // For prior to Moodle 4.4 to stick we need to set the class attribute like this
        $group->setAttributes(['class' => 'ml-continueformbuttons']);

        $html  = html_writer::end_div();
        $mform->addElement('html', $html);

        $PAGE->requires->js_call_amd('mod_minilesson/activitycontroller', 'continueconfirmation', [
            '.restore_lesson #' . $deletebtn->getAttribute('id'),
        ]);
    }

    public function get_element_value($elname) {
        $mform = $this->_form;
        return $mform->elementExists($elname) ? $mform->getElement($elname)->getValue() : null;
    }

}
