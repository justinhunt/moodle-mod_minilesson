<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use mod_minilesson\constants;

class passagegapfillform extends baseform {

    public $type = constants::TYPE_PGAPFILL;

    public function custom_definition() {
        $this->add_itemsettings_heading();
        $this->add_static_text('instructions', '', get_string('passagegapfilldesc', constants::M_COMPONENT));
        
        // Audio TTS or Custom
        $nossml = true;
        $hideiffield = false;   
        $hideifvalue = false;
        $nottsoption = true;
        // Audio options
        $this->add_voiceoptions(constants::POLLYOPTION, get_string('choosevoiceoption', constants::M_COMPONENT),
            $hideiffield, $hideifvalue, $nossml, $nottsoption);
        //TTS audio
        $this->add_ttsaudioselect(constants::POLLYVOICE, get_string('choosevoice', constants::M_COMPONENT));
        $this->_form->hideIf(constants::POLLYVOICE, constants::POLLYOPTION, 'eq', constants::TTS_NOTTS);
        
        //Custom  audio
        $customaudiolabel = get_string('customaudio', constants::M_COMPONENT);  
        $this->add_sentenceaudio(1, $customaudiolabel, false, 1);
        $this->_form->hideIf(constants::FILEANSWER.'1_audio', constants::POLLYOPTION, 'neq', constants::TTS_NOTTS);
        
        //passage
        $this->add_textarearesponse(constants::PASSAGEGAPFILL_PASSAGE, get_string('passagewithgaps', constants::M_COMPONENT), true);

        //other opts
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
        //add a hints option here:
        $this->add_dropdown(constants::PASSAGEGAPFILL_HINTS, get_string('hints', constants::M_COMPONENT),
            [0 => get_string('none'), 1 => 1, 2 => 2], 0);
        $this->add_checkbox(constants::PENALIZEHINTS,
        get_string('penalizehints', constants::M_COMPONENT),
         get_string('penalizehints_desc', constants::M_COMPONENT), 0);


    }
}