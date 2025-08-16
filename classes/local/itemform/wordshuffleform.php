<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use mod_minilesson\constants;

class wordshuffleform extends baseform {

    public $type = constants::TYPE_WORDSHUFFLE;

    public function custom_definition() {
        $this->add_itemsettings_heading();


        // Do we want TTS audio
        $this->_form->addElement('advcheckbox', constants::READSENTENCE,
            get_string('readsentences', constants::M_COMPONENT),
            get_string('readsentences_desc', constants::M_COMPONENT), [], [0, 1]);

        // Maybe add TTS Audio voices.
        $this->add_ttsaudioselect(constants::POLLYVOICE, get_string('choosevoice', constants::M_COMPONENT),
            constants::READSENTENCE, 0);

        $nossml = true;
        $this->add_voiceoptions(constants::POLLYOPTION, get_string('choosevoiceoption', constants::M_COMPONENT),
            constants::READSENTENCE, 0, $nossml);

        $this->add_static_text('instructions', '', get_string('wordshuffledesc', constants::M_COMPONENT));
        $this->add_sentenceprompt(1,get_string('sentenceprompts',constants::M_COMPONENT),true);
        $this->add_sentenceimage(1, null, false);
        $this->add_sentenceaudio(1, null, false);
        // $this->add_confirmchoice(constants::CONFIRMCHOICE, get_string('confirmchoice_formlabel', constants::M_COMPONENT));
        //$this->add_hidestartpage(constants::GAPFILLHIDESTARTPAGE, get_string('hidestartpage_desc', constants::M_COMPONENT));
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
        $this->add_allowretry(constants::GAPFILLALLOWRETRY, get_string('allowretry_desc', constants::M_COMPONENT));
    }
}