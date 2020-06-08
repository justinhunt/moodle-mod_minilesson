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
 * Grade Now for poodlltime plugin
 *
 * @package    mod_poodlltime
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 namespace mod_poodlltime;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/lib/formslib.php');
use \mod_poodlltime\constants;


/**
 * Event observer for mod_poodlltime
 *
 * @package    mod_poodlltime
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradenowform extends \moodleform{

    /**
     * Defines forms elements
     */
    public function definition() {
    	global $CFG;

        $mform = $this->_form;
		$mform->addElement('header','General','');
		
		//do fetch attemptid and n to help us make buttons
        $attemptid = $this->_customdata['attemptid'];
        $n = $this->_customdata['n'];
		
		$buttonarray=array();
		$buttonarray[] = &$mform->createElement('cancel','cancel',get_string('goback',constants::M_COMPONENT));
		$buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
		$theurl=$CFG->wwwroot . constants::M_URL . '/printattempt.php?n=' .$n . '&attemptid=' . $attemptid;
		$buttonarray[] = &$mform->createElement(
		    'static',
            'printattempt',
            '',
            \html_writer::link(
                $theurl,
                get_string('printattempt',constants::M_COMPONENT),
                array('id' => 'printattempt', 'target'=>'_blank', 'class'=>'btn btn-secondary', 'data-base-url' => $theurl)
            )
        );

		$mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);

	//	$mform->closeHeaderBefore('buttonar');
		
		   //-------------------------------------------------------------------------------
        // Adding the "general" fieldset, where all the common settings are showed,
		$mform->addElement('hidden', 'action');
		$mform->addElement('hidden', 'attemptid');
		$mform->addElement('hidden', 'n');
        $mform->addElement('hidden','returnurl');
        $mform->addElement('hidden', 'sessiontime',null,
				array('class'=>constants::M_GRADING_FORM_SESSIONTIME,'id'=>constants::M_GRADING_FORM_SESSIONTIME));
		$mform->addElement('hidden', 'sessionerrors',null,
				array('class'=>constants::M_GRADING_FORM_SESSIONERRORS,'id'=>constants::M_GRADING_FORM_SESSIONERRORS));
		$mform->addElement('hidden', 'wpm',null,
				array('class'=>constants::M_GRADING_FORM_WPM,'id'=>constants::M_GRADING_FORM_WPM));
		$mform->addElement('hidden', 'accuracy',null,
				array('class'=>constants::M_GRADING_FORM_ACCURACY,'id'=>constants::M_GRADING_FORM_ACCURACY));
		$mform->addElement('hidden', 'sessionscore',null,
				array('class'=>constants::M_GRADING_FORM_SESSIONSCORE,'id'=>constants::M_GRADING_FORM_SESSIONSCORE));
		$mform->addElement('hidden', 'sessionendword',null,
				array('class'=>constants::M_GRADING_FORM_SESSIONENDWORD,'id'=>constants::M_GRADING_FORM_SESSIONENDWORD));
        $mform->addElement('hidden', 'notes',null,
            array('class'=>constants::M_GRADING_FORM_NOTES,'id'=>constants::M_GRADING_FORM_NOTES));
        $mform->addElement('hidden', 'selfcorrections',null,
            array('class'=>constants::M_GRADING_FORM_SELFCORRECTIONS,'id'=>constants::M_GRADING_FORM_SELFCORRECTIONS));
		$mform->setType('action',PARAM_TEXT);
		$mform->setType('attemptid',PARAM_INT);
		$mform->setType('n',PARAM_INT);
        $mform->setType('returnurl',PARAM_URL);
		$mform->setType('sessiontime',PARAM_INT);
		$mform->setType('sessionerrors',PARAM_TEXT);
		$mform->setType('sessionscore',PARAM_INT);
		$mform->setType('accuracy',PARAM_INT);
		$mform->setType('wpm',PARAM_INT);
		$mform->setType('sessionendword',PARAM_INT);
        $mform->setType('notes',PARAM_TEXT);
        $mform->setType('selfcorrections',PARAM_TEXT);
        //-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
       // $this->add_action_buttons();
    }
}

