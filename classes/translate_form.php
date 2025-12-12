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

use moodleform;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
/**
 * Class translate_form
 *
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class translate_form extends moodleform {
    /**
     * Define the form.
     */
    public function definition() {
        global $DB;
        $mform = $this->_form;
        $cm = get_course_and_cm_from_cmid($this->optional_param('id', 0, PARAM_INT))[1];
        $minilesson = $DB->get_record($cm->modname, ['id' => $cm->instance], '*', MUST_EXIST);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'translateimportid');
        $mform->setType('translateimportid', PARAM_INT);

        $languages = utils::get_lang_options();
        $mform->addElement('select', 'sourcelanguage', get_string('importfromlang', constants::M_COMPONENT), $languages);
        $mform->setType('sourcelanguage', PARAM_TEXT);

        $mform->addElement('select', 'targetlanguage', get_string('importtolang', constants::M_COMPONENT), $languages);
        $mform->setType('targetlanguage', PARAM_TEXT);
        $mform->setDefault('targetlanguage', $minilesson->nativelang);

        $mform->addElement('submit', 'import', get_string('import'));

    }

    public function process_dynamic_submission() {
        $mform = $this->_form;
        $formdata = $this->get_data();
        if (!$formdata) {
            return null;
        }
        $redirecturl = new moodle_url(
            $mform->getAttribute('action'),
            (array) $formdata
        );
        return [
            'redirecturl' => $redirecturl->out(false)
        ];
    }
}
