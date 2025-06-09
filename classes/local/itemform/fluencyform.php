<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use mod_minilesson\constants;
use mod_minilesson\utils;

class fluencyform extends baseform {


    public $type = constants::TYPE_FLUENCY;

    public function custom_definition() {
        $this->add_itemsettings_heading();
        $mform = $this->_form;

        $this->add_ttsaudioselect(constants::POLLYVOICE, get_string('choosevoice', constants::M_COMPONENT),
        constants::READSENTENCE, 0);

        $nossml = true;
        $this->add_voiceoptions(constants::POLLYOPTION, get_string('choosevoiceoption', constants::M_COMPONENT),
        constants::READSENTENCE, 0, $nossml);
        $this->add_static_text('instructions', '', get_string('phraseresponses', constants::M_COMPONENT));
        $this->add_sentenceprompt(1, get_string('sentenceprompts', constants::M_COMPONENT), true);
        $this->add_sentenceimage(1, null, false);
        $this->add_sentenceaudio(1, null, false);
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
        $this->add_numericboxresponse(constants::FLUENCYCORRECTTHRESHOLD, get_string('correctthreshold', constants::M_COMPONENT));
        $mform->setDefault(constants::FLUENCYCORRECTTHRESHOLD, 90);
        $this->add_hidestartpage(constants::GAPFILLHIDESTARTPAGE, get_string('hidestartpage_desc', constants::M_COMPONENT));

    }

}
