<?php

/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use mod_minilesson\constants;

class multiaudioform extends baseform
{
    public $type = constants::TYPE_MULTIAUDIO;

    public function custom_definition()
    {
        $this->add_itemsettings_heading();
        $this->add_ttsaudioselect(constants::POLLYVOICE, get_string('choosemultiaudiovoice', constants::M_COMPONENT));
        $this->add_voiceoptions(constants::POLLYOPTION, get_string('choosevoiceoption', constants::M_COMPONENT));
        $this->add_showtextpromptoptions(constants::SHOWTEXTPROMPT, get_string('showoptionsastext', constants::M_COMPONENT), constants::TEXTPROMPT_WORDS);
        $this->add_correctanswer();
        $this->add_static_text('instructionsanswers', '', get_string('mcanswerresponses', constants::M_COMPONENT));
        $this->add_sentenceprompt(1, get_string('multichoiceanswers', constants::M_COMPONENT), true);
        $this->add_static_text('instructionsaudio', '', get_string('multiaudioaudioresponses', constants::M_COMPONENT));
        $this->add_sentenceaudio(1, get_string('multichoiceansweraudios', constants::M_COMPONENT), false);

        // $this->add_repeating_textboxes('sentence',5);
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
    }
}
