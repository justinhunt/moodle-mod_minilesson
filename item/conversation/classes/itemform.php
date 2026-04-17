<?php

/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace minilessonitem_conversation;

use mod_minilesson\local\itemform\baseform;

use mod_minilesson\constants;

class itemform extends baseform
{
    public function custom_definition()
    {
        $mform = $this->_form;
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('conversations_instructions1', constants::M_COMPONENT));
        $this->add_itemsettings_heading();
        $this->add_ttsaudioselect(constants::POLLYVOICE, get_string('choosevoice', constants::M_COMPONENT));
        $no_ssml = true;
        $this->add_voiceoptions(constants::POLLYOPTION, get_string('choosevoiceoption', constants::M_COMPONENT));
        $this->add_static_text('instructions', '', get_string('conversationdesc', constants::M_COMPONENT));
        $this->add_textarearesponse(1, get_string('sentenceprompts', constants::M_COMPONENT), true);
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
    }
}
