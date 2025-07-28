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
        $this->add_numericboxresponse(constants::TOTALMARKS, get_string('totalmarks', constants::M_COMPONENT), true);
        $mform->setDefault(constants::TOTALMARKS, 5);
        $this->add_static_text('totalmarks_instructions', '', get_string('totalmarks_instructions', constants::M_COMPONENT));
        $this->add_numericboxresponse(constants::TARGETWORDCOUNT, get_string('targetwordcount_title', constants::M_COMPONENT), false);
        $mform->setDefault(constants::TARGETWORDCOUNT, 60);
        $this->add_textarearesponse(constants::AUDIOCHAT_INSTRUCTIONS, get_string('audiochat_instructions', constants::M_COMPONENT), true);
        $mform->setDefault(constants::AUDIOCHAT_INSTRUCTIONS, get_string('audiochat_instructions_default', constants::M_COMPONENT));
        $this->add_textarearesponse(constants::AUDIOCHAT_ROLE, get_string('audiochat_role', constants::M_COMPONENT), true);
        $mform->setDefault(constants::AUDIOCHAT_ROLE, get_string('audiochat_role_default', constants::M_COMPONENT));
        // Feedback language.
        $this->add_languageselect(constants::AUDIOCHAT_NATIVE_LANGUAGE,
            get_string('audiochat_native_language', constants::M_COMPONENT),
            constants::M_LANG_ENUS
        );

        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
    }
}
