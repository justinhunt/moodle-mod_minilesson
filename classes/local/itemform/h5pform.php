<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use \mod_minilesson\constants;

class h5pform extends baseform
{

    public $type = constants::TYPE_H5P;

    public function custom_definition() {
        global $OUTPUT;

        $this->add_itemsettings_heading();
        $mform = $this->_form;
        $this->add_static_text('instructions', '', get_string('h5pforminstructions', constants::M_COMPONENT));
        // Set the total marks for the item.
        $this->add_numericboxresponse(constants::TOTALMARKS, get_string('totalmarks', constants::M_COMPONENT), true);
        $mform->setDefault(constants::TOTALMARKS, 5);

        // Adding the rest of mod_h5pactivity settings, spreading all them into this fieldset.
        $options = [];
        $options['accepted_types'] = ['.h5p'];
        $options['maxbytes'] = 0;
        $options['maxfiles'] = 1;
        $options['subdirs'] = 0;

        $mform->addElement('filemanager', constants::H5PFILE, get_string('package', 'mod_h5pactivity'), null, $options);
        $mform->addHelpButton(constants::H5PFILE, 'package', 'mod_h5pactivity');
        $mform->addRule(constants::H5PFILE, null, 'required');

        // Add a link to the Content Bank if the user can access.
         $course = get_course($this->moduleinstance->course);
         $cm         = get_coursemodule_from_instance('minilesson', $this->moduleinstance->id, $course->id, false, MUST_EXIST);
         $coursecontext = \context_course::instance($course->id);
         $context = \context_module::instance($cm->id);

        if (has_capability('moodle/contentbank:access', $coursecontext)) {
            $msg = null;

            // This is an existing activity. If the H5P file it's a referenced file from the content bank, a link for
            // displaying this specific content will be used instead of the generic link to the main page of the content bank.
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'mod_h5pactivity', constants::H5PFILE, 0, 'sortorder, itemid, filepath,
                filename', false);
            $file = reset($files);
            if ($file && $file->get_reference() != null) {
                $referencedfile = \repository::get_moodle_file($file->get_reference());
                if ($referencedfile->get_component() == 'contentbank') {
                    // If the attached file is a referencedfile in the content bank, display a link to open this content.
                    $url = new \moodle_url('/contentbank/view.php', ['id' => $referencedfile->get_itemid()]);
                    $msg = get_string('opencontentbank', 'mod_h5pactivity', $url->out());
                    $msg .= ' ' . $OUTPUT->help_icon('contentbank', 'mod_h5pactivity');
                }
            }

            if (!isset($msg)) {
                $url = new \moodle_url('/contentbank/index.php', ['contextid' => $coursecontext->id]);
                $msg = get_string('usecontentbank', 'mod_h5pactivity', $url->out());
                $msg .= ' ' . $OUTPUT->help_icon('contentbank', 'mod_h5pactivity');
            }

            $mform->addElement('static', 'contentbank', '', $msg);
        }
    }
}
