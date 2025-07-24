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

class multichoiceform extends baseform
{


    public $type = constants::TYPE_MULTICHOICE;

    public function custom_definition()
    {
        // add a heading for this form
        $this->add_itemsettings_heading();
        $this->add_showlistorreadoptions(constants::LISTENORREAD, get_string('listenorread', constants::M_COMPONENT), constants::LISTENORREAD_READ);
        $this->add_ttsaudioselect(
            constants::POLLYVOICE,
            get_string('choosemultiaudiovoice', constants::M_COMPONENT),
            constants::LISTENORREAD,
            [constants::LISTENORREAD_READ, constants::LISTENORREAD_IMAGE]
        );
        $this->add_voiceoptions(
            constants::POLLYOPTION,
            get_string('choosevoiceoption', constants::M_COMPONENT),
            constants::LISTENORREAD,
            [constants::LISTENORREAD_READ, constants::LISTENORREAD_IMAGE]
        );
        $this->add_confirmchoice(constants::CONFIRMCHOICE, get_string('confirmchoice_formlabel', constants::M_COMPONENT));

        $this->add_correctanswer();
        $this->add_static_text('instructionsanswers', '', get_string('mcanswerresponses', constants::M_COMPONENT));
        $this->add_sentenceprompt(1, get_string('multichoiceanswers', constants::M_COMPONENT), true);
        $this->add_static_text('instructionsimages', '', get_string('mcimageresponses', constants::M_COMPONENT));
        $this->add_sentenceimage(1, get_string('multichoiceanswerimages', constants::M_COMPONENT), false);
        $this->add_static_text('instructionsaudio', '', get_string('mcaudioresponses', constants::M_COMPONENT));
        $this->add_sentenceaudio(1, get_string('multichoiceansweraudios', constants::M_COMPONENT), false);

        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
    }

}
