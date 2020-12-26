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
 * The main minilesson configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_minilesson
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

use \mod_minilesson\constants;


/**
 * Module instance settings form
 */
class mod_minilesson_mod_form extends moodleform_mod {

    public function __construct($current, $section, $cm, $course, $ajaxformdata=null) {
        global $CFG;
        $this->current   = $current;
        $this->_instance = $current->instance;
        $this->_section  = $section;
        $this->_cm       = $cm;
        $this->_course   = $course;
        if ($this->_cm) {
            $this->context = context_module::instance($this->_cm->id);
        } else {
            $this->context = context_course::instance($course->id);
        }
        // Set the course format.
        require_once($CFG->dirroot . '/course/format/lib.php');
        $this->courseformat = course_get_format($course);
        // Guess module name if not set.
        if (is_null($this->_modname)) {
            $matches = array();
            if (!preg_match('/^mod_([^_]+)_mod_form$/', get_class($this), $matches)) {
                debugging('Rename form to mod_xx_mod_form, where xx is name of your module');
                print_error('unknownmodulename');
            }
            $this->_modname = $matches[1];
        }
        $this->init_features();
        moodleform::__construct('modedit.php', null, 'post', '', null, true, $ajaxformdata);
    }

    /**
     * Defines forms elements
     */
    public function definition() {
    	global $CFG, $COURSE;

        $mform = $this->_form;

        //-------------------------------------------------------------------------------
        // Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('minilessonname', constants::M_COMPONENT), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'minilessonname', constants::M_COMPONENT);

         // Adding the standard "intro" and "introformat" fields
        if($CFG->version < 2015051100){
        	$this->add_intro_editor();
        }else{
        	$this->standard_intro_elements();
		}

        //page layout options
        $layout_options = \mod_minilesson\utils::fetch_pagelayout_options();
        $mform->addElement('select', 'pagelayout', get_string('pagelayout', constants::M_COMPONENT),$layout_options);
        $mform->setDefault('pagelayout','standard');

        //time target
        $mform->addElement('hidden', 'timelimit',0);
        $mform->setType('timelimit', PARAM_INT);

/*
 * Later can add a proper time limit
        $timelimit_options = \mod_minilesson\utils::get_timelimit_options();
        $mform->addElement('select', 'timelimit', get_string('timelimit', constants::M_COMPONENT),
            $timelimit_options);
		$mform->setDefault('timelimit',60);
*/

		//add other editors
		//could add files but need the context/mod info. So for now just rich text
		$config = get_config(constants::M_COMPONENT);
		
		//The passage
		//$edfileoptions = minilesson_editor_with_files_options($this->context);
		$ednofileoptions = minilesson_editor_no_files_options($this->context);
		$opts = array('rows'=>'15', 'columns'=>'80');

		//welcome message [just kept cos its a pain in the butt to do this again from scratch if we ever do]
        /*
		$opts = array('rows'=>'6', 'columns'=>'80');
		$mform->addElement('editor','welcome_editor',get_string('welcomelabel',constants::M_COMPONENT),$opts, $ednofileoptions);
		$mform->setDefault('welcome_editor',array('text'=>$config->defaultwelcome, 'format'=>FORMAT_MOODLE));
		$mform->setType('welcome_editor',PARAM_RAW);
        */

        //showq titles
        $yesnooptions = array(1 => get_string('yes'), 0 => get_string('no'));
        $mform->addElement('select', 'showqtitles', get_string('showqtitles', constants::M_COMPONENT), $yesnooptions);
        $mform->setDefault('showqtitles',0);

		//Attempts
        $attemptoptions = array(0 => get_string('unlimited', constants::M_COMPONENT),
                            1 => '1',2 => '2',3 => '3',4 => '4',5 => '5',);
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', constants::M_COMPONENT), $attemptoptions);

        //tts options
        $langoptions = \mod_minilesson\utils::get_lang_options();
        $mform->addElement('select', 'ttslanguage', get_string('ttslanguage', constants::M_COMPONENT), $langoptions);
        $mform->setDefault('ttslanguage',$config->ttslanguage);

        //region
        $regionoptions = \mod_minilesson\utils::get_region_options();
        $mform->addElement('select', 'region', get_string('region', constants::M_COMPONENT), $regionoptions);
        $mform->setDefault('region',$config->awsregion);

        //prompt types
        $prompttypes = \mod_minilesson\utils::get_prompttype_options();
        $mform->addElement('select', 'richtextprompt', get_string('prompttype', constants::M_COMPONENT), $prompttypes);
        $mform->addHelpButton('richtextprompt', 'prompttype', constants::M_COMPONENT);
        $mform->setDefault('richtextprompt', $config->prompttype);


        // Grade.
        $this->standard_grading_coursemodule_elements();
        
        //grade options
        //for now we hard code this to latest attempt
        $mform->addElement('hidden', 'gradeoptions',constants::M_GRADELATEST);
        $mform->setType('gradeoptions', PARAM_INT);

        // Post attempt
        $mform->addElement('header', 'postattemptheader', get_string('postattemptheader',constants::M_COMPONENT));

        // Get the modules.
        if ($mods = get_course_mods($COURSE->id)) {
            $modinstances = array();
            foreach ($mods as $mod) {
                // Get the module name and then store it in a new array.
                if ($module = get_coursemodule_from_instance($mod->modname, $mod->instance, $COURSE->id)) {
                    // Exclude this Poodll Time activity (if it's already been saved.)
                    if (!isset($this->_cm->id) || $this->_cm->id != $mod->id) {
                        $modinstances[$mod->id] = $mod->modname.' - '.$module->name;
                    }
                }
            }
            asort($modinstances); // Sort by module name.
            $modinstances=array(0=>get_string('none'))+$modinstances;

            $mform->addElement('select', 'activitylink', get_string('activitylink', 'lesson'), $modinstances);
            $mform->addHelpButton('activitylink', 'activitylink', 'lesson');
            $mform->setDefault('activitylink', 0);
        }


        //-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
        //-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();
    }
	
	
    /**
     * This adds completion rules
	 * The values here are just dummies. They don't work in this project until you implement some sort of grading
	 * See lib.php minilesson_get_completion_state()
     */
	 function add_completion_rules() {
		$mform =& $this->_form;  
		$config = get_config(constants::M_COMPONENT);
    
		//timer options
        //Add a place to set a mimumum time after which the activity is recorded complete
       $mform->addElement('static', 'mingradedetails', '',get_string('mingradedetails', constants::M_COMPONENT));
       $options= array(0=>get_string('none'),20=>'20%',30=>'30%',40=>'40%',50=>'50%',60=>'60%',70=>'70%',80=>'80%',90=>'90%',100=>'40%');
       $mform->addElement('select', 'mingrade', get_string('mingrade', constants::M_COMPONENT), $options);
	   
		return array('mingrade');
	}
	
	function completion_rule_enabled($data) {
		return ($data['mingrade']>0);
	}
	
	public function data_preprocessing(&$form_data) {
		$ednofileoptions = minilesson_editor_no_files_options($this->context);
		$editors  = minilesson_get_editornames();
		 if ($this->current->instance) {
			$itemid = 0;
			foreach($editors as $editor){
				$form_data = file_prepare_standard_editor((object)$form_data,$editor, $ednofileoptions, $this->context,constants::M_COMPONENT,$editor, $itemid);
			}
		}
	}


}
