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

        $controls = [];
        $typeoptions = self::type_options();
        $controls[0] = $mform->createElement('selectyesno', 'enabled', get_string('contextmapping:enabled', constants::M_COMPONENT));
        $controls[1] = $mform->createElement('text', 'title', get_string('contextmapping:title', constants::M_COMPONENT),
            ['placeholder' => get_string('contextmapping:title_desc', constants::M_COMPONENT)]);
        $controls[2] = $mform->createElement('textarea', 'description', get_string('contextmapping:description', constants::M_COMPONENT),
            ['placeholder' => get_string('contextmapping:description_desc', constants::M_COMPONENT)]);
        $controls[3] = $mform->createElement('select', 'type', get_string('contextmapping:description', constants::M_COMPONENT), $typeoptions);
        $controls[4] = $mform->createElement('textarea', 'options', get_string('contextmapping:options', constants::M_COMPONENT),
            ['placeholder' => get_string('contextmapping:options_desc', constants::M_COMPONENT)]);

        foreach(self::mappings() as $fieldname) {
            $mform->setType("{$fieldname}[title]", PARAM_TEXT);
            $mform->disabledIf("{$fieldname}[options]", "{$fieldname}[type]", 'neq', end($typeoptions));
            $mform->addGroup($controls, $fieldname, $fieldname);
        }

        $this->add_action_buttons(false, 'Import JSON');
    }

    public static function mappings() {
        $availablecontext = [];
        $availablecontext[] = 'target_language'; // Data from the activity settings, language is required
        $availablecontext[] = 'user_topic'; // Sample data that the user might provide. eg "Your plan for the weekend"
        $availablecontext[] = 'user_level'; // Sample data that the user might provide. eg "A1" or "Intermediate"
        $availablecontext[] = 'user_text'; // Sample data that the user might provide. eg " One fine day I decided .."
        $availablecontext[] = 'user_keywords'; // Sample data that the user might provide. eg "big dog, cat, mouse, eat a horse"
        $availablecontext[] = 'user_customdata1'; // Sample data that the user might provide.
        $availablecontext[] = 'user_customdata2'; // Sample data that the user might provide.
        $availablecontext[] = 'user_customdata3'; // Sample data that the user might provide.
        return $availablecontext;
    }

    public static function type_options() {
        $types = ['text', 'textarea', 'dropdown'];
        return array_combine($types, $types);
    }

}

