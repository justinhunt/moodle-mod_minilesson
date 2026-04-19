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
 * Scatter mod_minilesson
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace minilessonitem_scatter;

use mod_minilesson\local\itemform\baseform;

use mod_minilesson\constants;

class itemform extends baseform {
    public function custom_definition() {
        global $CFG;
        $mform = $this->_form;
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('scatter_instructions1', constants::M_COMPONENT));

        // Add a heading for this form.
        $this->add_itemsettings_heading();
        $this->add_static_text(
            'enterscatteritems',
            '',
            get_string('enterscatteritems', constants::M_COMPONENT)
        );
        $this->add_textarearesponse(1, get_string('scatteritems', constants::M_COMPONENT), true);
        $this->add_checkbox(constants::SCATTERHINTRTL, get_string('scatterdefrtl', constants::M_COMPONENT),
            get_string('scatterdefrtl_desc', constants::M_COMPONENT));
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
    }
}
