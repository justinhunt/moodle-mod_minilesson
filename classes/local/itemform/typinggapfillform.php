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
 * Typing Gap Fill mod_minilesson
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_minilesson\local\itemform;

use mod_minilesson\constants;

class typinggapfillform extends baseform
{
    public $type = constants::TYPE_TGAPFILL;

    public function custom_definition()
    {
        $this->add_itemsettings_heading();
        $this->add_static_text('instructions', '', get_string('gapfillitemsdesc', constants::M_COMPONENT));
        $this->add_sentenceprompt(1, get_string('sentenceprompts', constants::M_COMPONENT), true);
        $this->add_checkbox(constants::GAPFILLHINTRTL, get_string('hintrtl', constants::M_COMPONENT),
            get_string('hintrtl_desc', constants::M_COMPONENT));
        $this->add_sentenceimage(1, null, false);
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
        $this->add_allowretry(constants::GAPFILLALLOWRETRY, get_string('allowretry_desc', constants::M_COMPONENT));
        $this->add_virtualkeyboard(constants::TGAPFILL_ENABLEVKEYBOARD, constants::TGAPFILL_CUSTOMKEYS);
        $this->add_hidestartpage(constants::GAPFILLHIDESTARTPAGE, get_string('hidestartpage_desc', constants::M_COMPONENT));
    }
}
