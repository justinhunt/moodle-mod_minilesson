<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use \mod_minilesson\constants;

class speakinggapfillform extends baseform {

    public $type = constants::TYPE_SGAPFILL;

    public function custom_definition() {
        $mform = $this->_form;
        $mform->addElement('advcheckbox',constants::READSENTENCE,
            get_string('readsentences',constants::M_COMPONENT),
            get_string('readsentences_desc',constants::M_COMPONENT),[],[0,1]);
        $this->add_voiceselect(constants::POLLYVOICE,get_string('choosevoice',constants::M_COMPONENT),constants::READSENTENCE,0);
        $no_ssml=true;
        $this->add_voiceoptions(constants::POLLYOPTION,get_string('choosevoiceoption',constants::M_COMPONENT),constants::READSENTENCE,0,$no_ssml);
        $this->add_static_text('instructions','',get_string('gapfillitemsdesc',constants::M_COMPONENT));
        $this->add_textarearesponse(1,get_string('sentenceprompts',constants::M_COMPONENT),true);
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
        $this->add_allowretry(constants::GAPFILLALLOWRETRY);
    }
}