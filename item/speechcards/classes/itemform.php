<?php

/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace minilessonitem_speechcards;

use mod_minilesson\local\itemform\baseform;

use function Aws\constantly;

use mod_minilesson\constants;

class itemform extends baseform
{
    public function custom_definition()
    {
        $mform = $this->_form;
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('sc_instructions1', constants::M_COMPONENT));
        $this->add_itemsettings_heading();
        $this->add_static_text('instructions', '', get_string('phraseresponses', constants::M_COMPONENT));
        $this->add_sentenceprompt(1, get_string('sentenceprompts', constants::M_COMPONENT), true);
        $this->add_sentenceaudio(1, null, false);
    }
}
