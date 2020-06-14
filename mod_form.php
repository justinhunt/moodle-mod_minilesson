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
 * The main poodlltime configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_poodlltime
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

use \mod_poodlltime\constants;


/**
 * Module instance settings form
 */
class mod_poodlltime_mod_form extends moodleform_mod {

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
        $mform->addElement('text', 'name', get_string('poodlltimename', constants::M_COMPONENT), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'poodlltimename', constants::M_COMPONENT);

         // Adding the standard "intro" and "introformat" fields
        if($CFG->version < 2015051100){
        	$this->add_intro_editor();
        }else{
        	$this->standard_intro_elements();
		}

        //page layout options
        $layout_options = \mod_poodlltime\utils::fetch_pagelayout_options();
        $mform->addElement('select', 'pagelayout', get_string('pagelayout', constants::M_COMPONENT),$layout_options);
        $mform->setDefault('pagelayout','embedded');

        //passagemaster
        $mform->addElement('advcheckbox', 'passagemaster', get_string('passagemaster', constants::M_COMPONENT), get_string('passagemaster_details', constants::M_COMPONENT));
        $mform->setDefault('passagemaster',0);

        // Adding passagekey field
        $mform->addElement('text', 'passagekey', get_string('passagekey', constants::M_COMPONENT), array('size'=>'25'));
        $mform->setType('passagekey', PARAM_TEXT);

        //time target
        $timelimit_options = \mod_poodlltime\utils::get_timelimit_options();
        $mform->addElement('select', 'timelimit', get_string('timelimit', constants::M_COMPONENT),
            $timelimit_options);
		//$mform->addElement('duration', 'timelimit', get_string('timelimit',constants::M_COMPONENT)));
		$mform->setDefault('timelimit',60);
		
		//add other editors
		//could add files but need the context/mod info. So for now just rich text
		$config = get_config(constants::M_COMPONENT);
		
		//The passage
		//$edfileoptions = poodlltime_editor_with_files_options($this->context);
		$ednofileoptions = poodlltime_editor_no_files_options($this->context);
		$opts = array('rows'=>'15', 'columns'=>'80');
		$mform->addElement('editor','passage_editor',get_string('passagelabel',constants::M_COMPONENT),$opts, $ednofileoptions);

		//Image to accompany passage in quiz part of activity
        $ppoptions = poodlltime_picturefile_options($this->context);
        $mform->addElement('filemanager', 'passagepicture', get_string('passagepicture',constants::M_COMPONENT), null, $ppoptions);

        //Show picture when reading
        $mform->addElement('advcheckbox', 'picwhenreading', get_string('picwhenreading', constants::M_COMPONENT), get_string('picwhenreading_details', constants::M_COMPONENT));
        $mform->setDefault('picwhenreading',$config->picwhenreading);

        // Adding passage level field
        $mform->addElement('text', 'level', get_string('level', constants::M_COMPONENT), array('size'=>'25'));
        $mform->setType('level', PARAM_TEXT);


        //The alternatives declaration
        $mform->addElement('textarea','alternatives',get_string("alternatives", constants::M_COMPONENT),
            'wrap="virtual" rows="15" cols="50"');
        $mform->setDefault('alternatives','');
        $mform->setType('alternatives',PARAM_RAW);

		//welcome and feedback
		$opts = array('rows'=>'6', 'columns'=>'80');
		$mform->addElement('editor','welcome_editor',get_string('welcomelabel',constants::M_COMPONENT),$opts, $ednofileoptions);
		$mform->addElement('editor','feedback_editor',get_string('feedbacklabel',constants::M_COMPONENT),$opts, $ednofileoptions);
		
		//defaults
		$mform->setDefault('passage_editor',array('text'=>'', 'format'=>FORMAT_PLAIN));
		$mform->setDefault('welcome_editor',array('text'=>$config->defaultwelcome, 'format'=>FORMAT_MOODLE));
		$mform->setDefault('feedback_editor',array('text'=>$config->defaultfeedback, 'format'=>FORMAT_MOODLE));
		
		//types
		$mform->setType('passage_editor',PARAM_RAW);
		$mform->setType('welcome_editor',PARAM_RAW);
		$mform->setType('feedback_editor',PARAM_RAW);
		
		// Adding targetwpm field
        $mform->addElement('text', 'targetwpm', get_string('targetwpm', constants::M_COMPONENT), array('size'=>'8'));
        $mform->setType('targetwpm', PARAM_INT);
		$mform->setDefault('targetwpm',$config->targetwpm);
        $mform->addHelpButton('targetwpm', 'targetwpm', constants::M_COMPONENT);
		
		//allow early exit
		$mform->addElement('advcheckbox', 'allowearlyexit', get_string('allowearlyexit', constants::M_COMPONENT), get_string('allowearlyexit_details', constants::M_COMPONENT));
		$mform->setDefault('allowearlyexit',$config->allowearlyexit);


		//Attempts
        $attemptoptions = array(0 => get_string('unlimited', constants::M_COMPONENT),
                            1 => '1',2 => '2',3 => '3',4 => '4',5 => '5',);
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', constants::M_COMPONENT), $attemptoptions);


		
		 // Grade.
        $this->standard_grading_coursemodule_elements();
        
        //grade options
        //for now we hard code this to latest attempt
        $mform->addElement('hidden', 'gradeoptions',constants::M_GRADELATEST);
        $mform->setType('gradeoptions', PARAM_INT);

        //human vs machine grade options
        $machinegradeoptions = \mod_poodlltime\utils::get_machinegrade_options();
        $mform->addElement('select', 'machgrademethod', get_string('machinegrademethod', constants::M_COMPONENT), $machinegradeoptions);
        $mform->setDefault('machgrademethod',$config->machinegrademethod);
        $mform->addHelpButton('machgrademethod', 'machinegrademethod', constants::M_COMPONENT);

        // Appearance.
        $mform->addElement('header', 'recordingaiheader', get_string('recordingaiheader',constants::M_COMPONENT));

        //Enable AI
        $mform->addElement('advcheckbox', 'enableai', get_string('enableai', constants::M_COMPONENT), get_string('enableai_details', constants::M_COMPONENT));
        $mform->setDefault('enableai',$config->enableai);

        //tts options
        $langoptions = \mod_poodlltime\utils::get_lang_options();
        $mform->addElement('select', 'ttslanguage', get_string('ttslanguage', constants::M_COMPONENT), $langoptions);
        $mform->setDefault('ttslanguage',$config->ttslanguage);

        //transcriber options
        $name = 'transcriber';
        $label = get_string($name, constants::M_COMPONENT);
        $options = \mod_poodlltime\utils::fetch_options_transcribers();
        $mform->addElement('select', $name, $label, $options);
        $mform->setDefault($name, $config->transcriber);


        //region
        $regionoptions = \mod_poodlltime\utils::get_region_options();
        $mform->addElement('select', 'region', get_string('region', constants::M_COMPONENT), $regionoptions);
        $mform->setDefault('region',$config->awsregion);

        //expiredays
        $expiredaysoptions = \mod_poodlltime\utils::get_expiredays_options();
        $mform->addElement('select', 'expiredays', get_string('expiredays', constants::M_COMPONENT), $expiredaysoptions);
        $mform->setDefault('expiredays',$config->expiredays);


        //Submit Raw Audio
        $mform->addElement('advcheckbox', 'submitrawaudio', get_string('submitrawaudio', constants::M_COMPONENT),
                get_string('submitrawaudio_details', constants::M_COMPONENT));
        $mform->setDefault('submitrawaudio', $config->submitrawaudio);


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

        // Post attempt evaluation display (human)
        $postattempt_options = \mod_poodlltime\utils::get_postattempt_options();
        $mform->addElement('select', 'humanpostattempt', get_string('evaluationview', constants::M_COMPONENT),
            $postattempt_options);
        $mform->setType('humanpostattempt', PARAM_INT);
        $mform->setDefault('humanpostattempt',$config->humanpostattempt);

        // Post attempt evaluation display (machine)
        /*
        $mform->addElement('select', 'machinepostattempt', get_string('machinepostattempt', constants::M_COMPONENT),
            $postattempt_options);
        $mform->setType('machinepostattempt', PARAM_INT);
        $mform->setDefault('machinepostattempt',$config->machinepostattempt);
        */
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
	 * See lib.php poodlltime_get_completion_state()
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
		//$edfileoptions = poodlltime_editor_with_files_options($this->context);
		$ednofileoptions = poodlltime_editor_no_files_options($this->context);
		$editors  = poodlltime_get_editornames();
		 if ($this->current->instance) {
			$itemid = 0;
			foreach($editors as $editor){
				$form_data = file_prepare_standard_editor((object)$form_data,$editor, $ednofileoptions, $this->context,constants::M_COMPONENT,$editor, $itemid);
			}
		}

		//passage picture
        $ppoptions = poodlltime_picturefile_options($this->context);
        $draftitemid = file_get_submitted_draft_itemid('passagepicture');
        file_prepare_draft_area($draftitemid, $this->context->id, constants::M_COMPONENT, constants::PASSAGEPICTURE_FILEAREA, 0,
            $ppoptions);
        if(is_array($form_data)){
            $form_data['passagepicture']=$draftitemid;
        }else {
            $form_data->passagepicture = $draftitemid;
        }
	}


}
