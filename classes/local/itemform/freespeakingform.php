<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use \mod_minilesson\constants;

class freespeakingform extends baseform {

    public $type = constants::TYPE_FREESPEAKING;

    public function custom_definition() {
        $this->add_itemsettings_heading();
        $mform = $this->_form;
        $this->add_static_text('instructions','',get_string('freespeakingdesc',constants::M_COMPONENT));
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
    }
}