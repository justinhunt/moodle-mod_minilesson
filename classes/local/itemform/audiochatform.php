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

class audiochatform extends baseform {

    public $type = constants::TYPE_AUDIOCHAT;

    public function custom_definition() {
        global $CFG;

        $this->add_itemsettings_heading();
        $mform = $this->_form;
        $this->add_static_text('instructions', '', get_string('audiochatdesc', constants::M_COMPONENT));
        
        // Total marks and target word count
        $this->add_numericboxresponse(constants::TOTALMARKS, get_string('totalmarks', constants::M_COMPONENT), true);
        $mform->setDefault(constants::TOTALMARKS, 5);
        $this->add_static_text('totalmarks_instructions', '', get_string('totalmarks_instructions', constants::M_COMPONENT));
        $this->add_numericboxresponse(constants::TARGETWORDCOUNT, get_string('targetwordcount_title', constants::M_COMPONENT), false);
        $mform->setDefault(constants::TARGETWORDCOUNT, 60);

        // The topic for the audio chat.
        $this->add_textarearesponse(constants::AUDIOCHAT_TOPIC, get_string('audiochat_topic', constants::M_COMPONENT), true);
        $mform->setDefault(constants::AUDIOCHAT_TOPIC, get_string('audiochat_topic_default', constants::M_COMPONENT));

        // The role of the AI.
        $this->add_textarearesponse(constants::AUDIOCHAT_ROLE, get_string('audiochat_role', constants::M_COMPONENT), true);
        $mform->setDefault(constants::AUDIOCHAT_ROLE, get_string('audiochat_role_default', constants::M_COMPONENT));

        // The voice of the AI.
        $options = ['alloy' => 'Alloy', 'ash' => 'Ash', 'ballad' => 'Ballad',
            'coral' => 'Coral', 'echo' => 'Echo', 'sage' => 'Sage', 'shimmer' => 'Shimmer', 'verse' => 'Verse'];
        $this->add_dropdown(constants::AUDIOCHAT_VOICE,
            get_string('audiochat_voice',  constants::M_COMPONENT),
            $options, 'alloy');

         // Students native language
        $this->add_languageselect(constants::AUDIOCHAT_NATIVE_LANGUAGE,
            get_string('audiochat_native_language', constants::M_COMPONENT),
            constants::M_LANG_ENUS
        );

        // The instructions template for the audio chat
        $this->add_textarearesponse(constants::AUDIOCHAT_INSTRUCTIONS, get_string('audiochat_instructions', constants::M_COMPONENT), true);
        $mform->setDefault(constants::AUDIOCHAT_INSTRUCTIONS, get_string('audiochat_instructions_default', constants::M_COMPONENT));
        $this->add_static_text('audiochat_instructions_instructions', '', get_string('audiochat_instructions_instructions', constants::M_COMPONENT));

        // The instructions for grading the audio chat
        $this->add_textarearesponse(constants::AUDIOCHAT_GRADEINSTRUCTIONS, get_string('audiochat_gradeinstructions', constants::M_COMPONENT), false);
        $mform->setDefault(constants::AUDIOCHAT_GRADEINSTRUCTIONS, get_string('audiochat_gradeinstructions_default', constants::M_COMPONENT));
        $this->add_static_text('audiochat_gradeinstructions_instructions', '', get_string('audiochat_gradeinstructions_instructions', constants::M_COMPONENT));

        // Auto Response
        $mform->addElement('advcheckbox', constants::AUDIOCHAT_AUTORESPONSE,
            get_string('audiochat_autosend', constants::M_COMPONENT),
            get_string('audiochat_autosend_desc', constants::M_COMPONENT), [], [0, 1]);
        $mform->setDefault(constants::AUDIOCHAT_AUTORESPONSE, 1);

        //Allow Retry
        $this->add_allowretry(constants::AUDIOCHAT_ALLOWRETRY, get_string('allowretry_desc', constants::M_COMPONENT));

        // Time limit if we need one
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));

        // The custom AI data fields
        $this->add_textarearesponse(constants::AUDIOCHAT_AIDATA1, get_string('audiochat_aidata1', constants::M_COMPONENT), false);
        $mform->setDefault(constants::AUDIOCHAT_AIDATA1, '');
        $this->add_textarearesponse(constants::AUDIOCHAT_AIDATA2, get_string('audiochat_aidata2', constants::M_COMPONENT), false);
        $mform->setDefault(constants::AUDIOCHAT_AIDATA2, '');

    }
}
