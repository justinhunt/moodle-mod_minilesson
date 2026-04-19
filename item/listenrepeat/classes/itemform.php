<?php

/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace minilessonitem_listenrepeat;

use mod_minilesson\local\itemform\baseform;

use mod_minilesson\constants;
use mod_minilesson\utils;

class itemform extends baseform
{
    public const ITEMCLASS = itemtype::class;

    public function custom_definition()
    {
        $mform = $this->_form;
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('lr_instructions1', constants::M_COMPONENT));
        $this->add_itemsettings_heading();
        $this->add_showtextpromptoptions(constants::SHOWTEXTPROMPT, get_string('showtextprompt', constants::M_COMPONENT));
        $this->add_ttsaudioselect(constants::POLLYVOICE, get_string('choosevoice', constants::M_COMPONENT));
        $this->add_voiceoptions(constants::POLLYOPTION, get_string('choosevoiceoption', constants::M_COMPONENT));
        //$textpromptoptions=utils::fetch_options_textprompt();
        //$this->add_dropdown(constants::SHOWTEXTPROMPT,get_string('showtextprompt',constants::M_COMPONENT),$textpromptoptions);
        $this->add_static_text('instructions', '', get_string('phraseresponses', constants::M_COMPONENT));
        $this->add_sentenceprompt(1, get_string('sentenceprompts', constants::M_COMPONENT), true);
        $this->add_sentenceimage(1, null, false);
        $this->add_sentenceaudio(1, null, false);
        $this->add_hidestartpage(constants::GAPFILLHIDESTARTPAGE, get_string('hidestartpage_desc', constants::M_COMPONENT));
        $this->add_textarearesponse(constants::ALTERNATES, get_string('alternates', constants::M_COMPONENT), false);
        $this->add_static_text('alternates_instructions', '', get_string('pr_alternates_instructions', constants::M_COMPONENT));
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
    }
}
