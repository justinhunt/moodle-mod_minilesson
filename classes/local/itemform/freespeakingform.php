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
        global $CFG;

        $this->add_itemsettings_heading();
        $mform = $this->_form;
        $this->add_static_text('instructions', '', get_string('freespeakingdesc', constants::M_COMPONENT));
        $this->add_numericboxresponse(1, get_string('targetwordcount_instructions', constants::M_COMPONENT), false);
        $this->add_textarearesponse(1, get_string('aigrade_instructions', constants::M_COMPONENT), true);
        $this->add_textarearesponse(2, get_string('aifeedback_instructions', constants::M_COMPONENT), true);
        $this->add_relevanceoptions(constants::RELEVANCE, get_string('relevancetype', constants::M_COMPONENT),
        constants::RELEVANCETYPE_NONE);
        $this->add_textarearesponse(3, get_string('modelanswer', constants::M_COMPONENT), false);
        $m35 = $CFG->version >= 2018051700;
        if ($m35) {
            $mform->hideIf("customtext3", constants::RELEVANCE, 'neq', constants::RELEVANCETYPE_MODELANSWER);
        } else {
            $mform->disabledIf("customtext3", constants::RELEVANCE, 'neq', constants::RELEVANCETYPE_MODELANSWER);
        }
        
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
    }
}
