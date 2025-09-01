<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use mod_minilesson\constants;

class freespeakingform extends baseform {

    public $type = constants::TYPE_FREESPEAKING;

    public function custom_definition() {
        global $CFG, $PAGE;

        $this->add_itemsettings_heading();
        $mform = $this->_form;
        $this->add_static_text('instructions', '', get_string('freespeakingdesc', constants::M_COMPONENT));
        $this->add_numericboxresponse(constants::TOTALMARKS, get_string('totalmarks', constants::M_COMPONENT), true);
        $mform->setDefault(constants::TOTALMARKS, 5);
        $this->add_static_text('freespeakingtotalmarks_instructions', '', get_string('totalmarks_instructions', constants::M_COMPONENT));
        $this->add_numericboxresponse(constants::TARGETWORDCOUNT, get_string('targetwordcount_title', constants::M_COMPONENT), false);
        $mform->setDefault(constants::TARGETWORDCOUNT, 60);

        $options = [
            0 => '--',
            1 => get_string('default'),
            2 => get_string('freespeaking:gradingprompt1', constants::M_COMPONENT),
            3 => get_string('freespeaking:gradingprompt2', constants::M_COMPONENT),
        ];
        $mform->addElement('select', constants::FREESPEAKING_GRADINGSELECTION, get_string('aigrade_instructions', constants::M_COMPONENT), $options,
            ['data-name' => 'gradingaiprompt', 'data-type' => 'freespeaking']);
        $mform->setDefault(constants::FREESPEAKING_GRADINGSELECTION, 1);

        $this->add_textarearesponse(constants::AIGRADE_INSTRUCTIONS, '', true);
        $mform->getElement(constants::AIGRADE_INSTRUCTIONS)->updateAttributes(['data-name' => 'aigrade_instructions']);
        $mform->setDefault(constants::AIGRADE_INSTRUCTIONS, get_string('freespeaking:gradingprompt_dec1', constants::M_COMPONENT));

        $options = [
            0 => '--',
            1 => get_string('default'),
            2 => get_string('freespeaking:feedbackprompt1', constants::M_COMPONENT),
        ];
        $mform->addElement('select', constants::FREESPEAKING_FEEDBACKSELECTION, get_string('aigrade_feedback', constants::M_COMPONENT), $options,
            ['data-name' => 'feedbackaiprompt', 'data-type' => 'freespeaking',]);
        $mform->setDefault(constants::FREESPEAKING_FEEDBACKSELECTION, 1);

        $this->add_textarearesponse(constants::AIGRADE_FEEDBACK, '', true);
        $mform->getElement(constants::AIGRADE_FEEDBACK)->updateAttributes(['data-name' => 'aigrade_feedback']);
        $mform->setDefault(constants::AIGRADE_FEEDBACK, get_string('freespeaking:feedbackprompt_dec1', constants::M_COMPONENT));
        // Feedback language.
        $this->add_languageselect(constants::AIGRADE_FEEDBACK_LANGUAGE,
            get_string('aigrade_feedback_language', constants::M_COMPONENT),
            constants::M_LANG_ENUS
        );

        $this->add_relevanceoptions(constants::RELEVANCE, get_string('relevancetype', constants::M_COMPONENT),
        constants::RELEVANCETYPE_NONE);
        $this->add_textarearesponse(constants::AIGRADE_MODELANSWER, get_string('aigrade_modelanswer', constants::M_COMPONENT), false);
        $m35 = $CFG->version >= 2018051700;
        if ($m35) {
            $mform->hideIf(constants::AIGRADE_MODELANSWER, constants::RELEVANCE, 'neq', constants::RELEVANCETYPE_MODELANSWER);
        } else {
            $mform->disabledIf(constants::AIGRADE_MODELANSWER, constants::RELEVANCE, 'neq', constants::RELEVANCETYPE_MODELANSWER);
        }
        
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));

        $this->add_textarearesponse(constants::FREESPEAKING_TOPIC, get_string('topic_placeholder', constants::M_COMPONENT),  false);

        $this->add_textarearesponse(constants::FREESPEAKING_AIDATA1, get_string('aidata1_placeholder', constants::M_COMPONENT), false);
        $mform->setDefault(constants::FREESPEAKING_AIDATA1, '');
        $this->add_textarearesponse(constants::FREESPEAKING_AIDATA2, get_string('aidata2_placeholder', constants::M_COMPONENT), false);
        $mform->setDefault(constants::FREESPEAKING_AIDATA2, '');

        $this->add_checkbox(constants::FREESPEAKING_HIDECORRECTION, get_string('hidecorrection', constants::M_COMPONENT), null, 0);

        $options  = [
            1 => get_string('starrating', constants::M_COMPONENT),
            2 => get_string('percentagescore', constants::M_COMPONENT)
        ];
        $this->add_dropdown(constants::FREESPEAKING_SHOWGRADE, get_string("showgrade", constants::M_COMPONENT), $options, 1);

        $options  = [
            1 => get_string('detailedresults', constants::M_COMPONENT),
            2 => get_string('basciresult', constants::M_COMPONENT)
        ];
        $this->add_dropdown(constants::FREESPEAKING_SHOWRESULT, get_string('showresult', constants::M_COMPONENT), $options, 1);

        $PAGE->requires->js_call_amd(constants::M_COMPONENT.'/aiprompt', 'init');
    }
}
