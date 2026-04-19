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

namespace minilessonitem_passagereading;

use mod_minilesson\local\itemform\baseform;

use mod_minilesson\constants;

class itemform extends baseform
{
    public function custom_definition()
    {
        $mform = $this->_form;
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('passagereading_instructions1', constants::M_COMPONENT));
        $this->add_itemsettings_heading();
        $this->add_static_text('instructions', '', get_string('passagereadingdesc', constants::M_COMPONENT));
        $this->add_textarearesponse(\minilessonitem_passagereading\itemtype::PASSAGE, get_string('passagetoread', constants::M_COMPONENT), true);
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
        $this->add_numericboxresponse(constants::TOTALMARKS, get_string('totalmarks', constants::M_COMPONENT), true);
        $mform->setDefault(constants::TOTALMARKS, 5);
        $this->add_static_text('passagereadingtotalmarks_instructions', '', get_string('pr_totalmarks_instructions', constants::M_COMPONENT));
        $this->add_textarearesponse(constants::ALTERNATES, get_string('alternates', constants::M_COMPONENT), false);
        $this->add_static_text('alternates_instructions', '', get_string('pr_alternates_instructions', constants::M_COMPONENT));
    }
}
