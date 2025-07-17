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

use moodle_exception;
use moodleform;

require_once($CFG->libdir . '/formslib.php');

class aigen_contextform extends moodleform {

    public function definition() {
        $mform = $this->_form;
        $keyname = $this->optional_param('keyname', null, PARAM_FILE);

        $lessontemplates = aigen::fetch_lesson_templates();
        if (!array_key_exists($keyname, $lessontemplates)) {
            throw new moodle_exception('Invalid template keyname', constants::M_COMPONENT);
        }
        $thetemplate = $lessontemplates[$keyname];
        if (!isset($thetemplate['config']) || !isset($thetemplate['config']->fieldmappings)) {
            throw new moodle_exception('Invalid template structure', constants::M_COMPONENT);
        }
        $mappings = $thetemplate['config']->fieldmappings;
        foreach($mappings as $fieldname => $fieldmapping) {
            if (!empty($fieldmapping->enabled)) {
                switch($fieldmapping->type) {
                    case 'dropdown':
                        $options = array_combine($fieldmapping->options, $fieldmapping->options) ;
                        $mform->addElement('select', $fieldname, $fieldmapping->title, $options);
                        break;
                    case 'textarea':
                        $mform->addElement('textarea', $fieldname, $fieldmapping->title);
                        break;
                    case 'text':
                    default:
                        $mform->addElement('text', $fieldname, $fieldmapping->title);
                        break;
                }
                if (!empty($fieldmapping->description)) {
                    $mform->addElement('static', "{$fieldname}_desc", "", $fieldmapping->description);
                }
            }
        }
        $mform->disable_form_change_checker();

        $mform->addElement('html', get_string('generationnotice', constants::M_COMPONENT));
    }
}
