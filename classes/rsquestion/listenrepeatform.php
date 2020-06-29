<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_poodlltime\rsquestion;

use \mod_poodlltime\constants;
use \mod_poodlltime\utils;

class listenrepeatform extends baseform
{

    public $type = constants::TYPE_LISTENREPEAT;

    public function custom_definition() {
        //nothing here
        $this->add_static_text('instructions','','Enter a list of sentences in the text area below');
        $this->add_textarearesponse(1,'sentences');
        $this->add_voiceselect(constants::POLLYVOICE,get_string('choosevoice',constants::M_COMPONENT));
        $textpromptoptions=utils::fetch_options_textprompt();
        $this->add_dropdown(constants::SHOWTEXTPROMPT,get_string('showtextprompt',constants::M_COMPONENT),$textpromptoptions);
    }

}