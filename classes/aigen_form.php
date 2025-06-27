<?php
/**
 * Helper.
 *
 * @package mod_minilesson
 * @author  Justin Hunt 
 */
namespace mod_minilesson;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use \mod_minilesson\constants;
use \mod_minilesson\utils;

/**
 * AIGEN form
 *
 * @package mod_minilesson
 * @author  Justin Hunt
 */
class aigen_form extends \moodleform {

   public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('textarea', 'importjson', 'Import JSON', array('style'=>'width: 100%; max-width: 1200px;'));
        $mform->setDefault('importdata', '');
        $mform->setType('importjson', PARAM_RAW);
        $this->add_action_buttons(false, 'Import JSON');
    }

}
