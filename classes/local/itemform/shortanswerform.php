<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use \mod_minilesson\constants;

class shortanswerform extends baseform
{

    public $type = constants::TYPE_SHORTANSWER;

    public function custom_definition() {
        $this->add_itemsettings_heading();
        //all answers are correct
        $this->add_static_text('instructions','',get_string('enterresponses',constants::M_COMPONENT));
        $this->add_textarearesponse(1,get_string('correctresponses',constants::M_COMPONENT),true);
        $this->add_textarearesponse(constants::ALTERNATES, get_string('alternates', constants::M_COMPONENT), false);
        $this->add_static_text('alternates_instructions', '', get_string('pr_alternates_instructions', constants::M_COMPONENT));

    }

}