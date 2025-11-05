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

use core\task\manager;
use moodle_exception;
use moodleform;
use stdClass;

require_once($CFG->libdir . '/formslib.php');

class aigen_contextform extends moodleform {

    /**
        * AIGEN actions
    */
    const AIGEN_LIST = 0;
    const AIGEN_SUBMIT = 1;

    public function definition() {
        $mform = $this->_form;
        $templateid = $this->optional_param('templateid', null, PARAM_INT);

        $lessontemplates = aigen::fetch_lesson_templates();
        if (!array_key_exists($templateid, $lessontemplates)) {
            throw new moodle_exception('Invalid template templateid', constants::M_COMPONENT);
        }
        $thetemplate = $lessontemplates[$templateid];
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

    public function process_dynamic_submission()
    {
        global $DB, $USER;
        if (!$this->is_cancelled() && $this->is_submitted() && $this->is_validated()) {
            $formdata = $this->get_data();

            $id = $this->optional_param('id', 0, PARAM_INT);
            $action = $this->optional_param('action', self::AIGEN_LIST, PARAM_INT);
            $templateid = $this->optional_param('templateid', 0, PARAM_INT);
            $moduleinstance = null;
            if ($id) {
                $cm = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
                $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
            }

            if ($action == self::AIGEN_SUBMIT && !empty($moduleinstance)) {
                // Sample Data -  will beoverwritten by form submission
                $contextdata = [
                    'target_language' => $moduleinstance->ttslanguage,
                    'user_topic' => '',
                    'user_level' => '',
                    'user_text' => '',
                    'user_keywords' => '',
                    'user_customdata1' => '',
                    'user_customdata2' => '',
                    'user_customdata3' => '',
                ];

                foreach (aigen_form::mappings() as $fieldname) {
                    if (isset($formdata->{$fieldname})) {
                        $contextdata[$fieldname] = $formdata->{$fieldname};
                    }
                }

                $record = new stdClass;
                $record->minilessonid = $moduleinstance->id;
                $record->templateid = $templateid;
                $record->contextdata = json_encode($contextdata);
                $record->timecreated = time();
                $record->id = $DB->insert_record('minilesson_template_usages', $record);

                $task = new task\process_aigen;
                $task->set_component(constants::M_COMPONENT);
                $task->set_custom_data(['usageid' => $record->id]);
                $task->set_userid($USER->id);
                manager::queue_adhoc_task($task);

                return 'submitted';
            }
        }
        return false;
    }

}
