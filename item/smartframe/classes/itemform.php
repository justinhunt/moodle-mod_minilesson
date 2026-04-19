<?php

/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace minilessonitem_smartframe;

use mod_minilesson\local\itemform\baseform;

use mod_minilesson\constants;

class itemform extends baseform
{
    public function custom_definition()
    {
        $mform = $this->_form;
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('smartframe_instructions1', constants::M_COMPONENT));
        $this->add_itemsettings_heading();
        $this->add_textboxresponse(1, 'smartframeurl', true);
    }
}
