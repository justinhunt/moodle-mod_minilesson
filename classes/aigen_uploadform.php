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

use context_user;
use moodleform;
use stdClass;

require_once($CFG->libdir . '/formslib.php');

class aigen_uploadform extends moodleform {

    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('filemanager', 'templates',
            get_string('uploadtemplate', constants::M_COMPONENT),
            ['subdirs' => 0, 'maxfiles' => 2, 'accepted_types' => 'json']
        );

        $mform->addRule('templates', get_string('required'), 'required');

        $this->add_action_buttons(true, get_string('upload'));
    }

    public function set_data_for_dynamic_submission()
    {
        $formdata = [
            'id' => $this->optional_param('id', null, PARAM_INT),
            'action' => $this->optional_param('action', null, PARAM_ALPHA),
        ];
        $this->set_data($formdata);
    }

    public function process_dynamic_submission()
    {
        global $DB, $USER;
        if (!$this->is_cancelled() && $this->is_submitted() && $this->is_validated()) {

            $formdata = $this->get_data();
            $context = context_user::instance($USER->id);
            $template = new stdClass;

            $fs = get_file_storage();
            $uploadedfiles = $fs->get_area_files($context->id, 'user', 'draft', $formdata->templates, 'id DESC', false);
            foreach($uploadedfiles as $uploadedfile) {
                if (strpos($uploadedfile->get_filename(), '_template') !== false) {
                    $template->template = $uploadedfile->get_content();
                }
                if (strpos($uploadedfile->get_filename(), '_config') !== false) {
                    $template->config = $uploadedfile->get_content();
                }
            }

            return self::upsert_template($template);
        }
        return false;
    }

    public function validation($data, $files) {
        global $USER;
        $context = context_user::instance($USER->id);
        $errors = parent::validation($data, $files);

        $fs = get_file_storage();
        $uploadedfiles = $fs->get_area_files($context->id, 'user', 'draft', $data['templates'], 'id DESC', false);
        if (!$uploadedfiles || count($uploadedfiles) < 2) {
            $errors['templates'] = get_string('error:atleast2jsonfiles', constants::M_COMPONENT);
        } else {
            $templatejsonfile = $configjsonfile = false;
            foreach($uploadedfiles as $uploadedfile) {
                if (strpos($uploadedfile->get_filename(), '_template') !== false) {
                    $templatejsonfile = $uploadedfile;
                }
                if (strpos($uploadedfile->get_filename(), '_config') !== false) {
                    $configjsonfile = $uploadedfile;
                }
            }
            if (!$templatejsonfile) {
                $errors['templates'][] = get_string('error:templatefilenotuploaded', constants::M_COMPONENT);
            } else {
                $templatejson = json_decode($templatejsonfile->get_content());
                if (json_last_error()) {
                    $errors['templates'][] = get_string('error:templatefilejsonparsingfailed', constants::M_COMPONENT, $templatejsonfile->get_filename());
                }
            }
            if (!$configjsonfile) {
                $errors['templates'][] = get_string('error:configfilenotuploaded', constants::M_COMPONENT);
            } else {
                $configjson = json_decode($configjsonfile->get_content());
                if (json_last_error()) {
                    $errors['templates'][] = get_string('error:configfilejsonparsingfailed', constants::M_COMPONENT, $configjsonfile->get_filename());
                } else {
                    if (empty($configjson->uniqueid)) {
                        $errors['templates'][] = get_string('error:configfile:uniqueidmissing', constants::M_COMPONENT);
                    }
                    if (empty($configjson->lessonTitle)) {
                        $errors['templates'][] = get_string('error:configfile:lessontitlemissing', constants::M_COMPONENT);
                    }
                    if (empty($configjson->lessonDescription)) {
                        $errors['templates'][] = get_string('error:configfile:lessondescriptionmissing', constants::M_COMPONENT);
                    }
                }
            }
            if (isset($errors['templates']) && is_array($errors['templates'])) {
                $errors['templates'] = join('<br>', $errors['templates']);
            }
        }

        return $errors;
    }

    public static function upsert_template(stdClass $template) {
        global $DB;
        $jsonconfig = json_decode($template->config);
        if (!json_last_error()) {
            if (!isset($template->name)) {
                $template->name = $jsonconfig->lessonTitle;
            }
            if (!isset($template->description)) {
                $template->description = $jsonconfig->lessonDescription;
            }
            if (isset($jsonconfig->version)) {
                $template->version = $jsonconfig->version;
            }
            $template->uniqueid = $jsonconfig->uniqueid;
        }
        $jsontemplate = json_decode($template->template);
        if (!json_last_error() && !empty($jsontemplate->files)) {
            // Files will be an array of fileareas each containing of files: filename = file content.
            // We don't want the file content in the template because its saved in DB, so we replace it with a placeholder'QQQQ'.
            // Test case: we want to translate an existing activity (and keep the images)
            $clearfiles = true;
            // If template name contains 'translate' we assume we want to keep the files.
            if (stripos($template->name, 'translate') !== false) {
                $clearfiles = false;
            }
            if ($clearfiles) {
                foreach ($jsontemplate->files as $fileareas) {
                    foreach ($fileareas as $j => $filearea) {
                        $fileareas->{$j} = array_fill_keys(array_keys((array) $filearea), 'QQQQ');
                    }
                }
            }
            // Encod the template
            $template->template = json_encode($jsontemplate, JSON_PRETTY_PRINT);
        }
        if (!isset($template->version)) {
            $template->version = 0;
        }

        // Save the template
        if (isset($template->uniqueid) && $templates = $DB->get_records('minilesson_templates', ['uniqueid' => $template->uniqueid], 'version DESC')) {
            foreach ($templates as $temp) {
                if ($temp->version == $template->version) {
                    return $temp;// If same version found then no need to update
                }
                $temp->config = $template->config;
                $temp->template = $template->template;
                $temp->version = $template->version;
                $template = $temp;
                break;
            }
        }
        if (!empty($template->id)) {
            $template->timemodified = time();
            $DB->update_record('minilesson_templates', $template);
        } else {
            $template->timecreated = time();
            $template->timemodified = 0;
            $template->id = $DB->insert_record('minilesson_templates', $template);
        }

        return $template;
    }

}
