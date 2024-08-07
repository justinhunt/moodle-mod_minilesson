<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use \mod_minilesson\constants;

class passagereadingform extends baseform {

    public $type = constants::TYPE_PASSAGEREADING;

    public function custom_definition() {
        $this->add_itemsettings_heading();
        $mform = $this->_form;
        $this->add_static_text('instructions','',get_string('passagereadingdesc',constants::M_COMPONENT));
        $this->add_textarearesponse(1,get_string('passagetoread',constants::M_COMPONENT),true);
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
    }
}