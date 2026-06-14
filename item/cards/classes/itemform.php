<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace minilessonitem_cards;

use mod_minilesson\constants;
use mod_minilesson\local\itemform\baseform;

/**
 * Class itemform
 *
 * @package    minilessonitem_cards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemform extends baseform {

    public function custom_definition() {
        $mform = $this->_form;

        // Set default instructions in the standard instructions area
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('cards_instructions1', 'minilessonitem_cards'));

        // Add Item Settings Heading - to separate cards specific fields from standard fields
        $this->add_itemsettings_heading();

        // Add Instructions for using this form
        $this->add_static_text('instructions', '', get_string('cardsforminstructions', 'minilessonitem_cards'));

        // This is the main area for words/phrases and related text on the screen.
        // A single line equals a card. Multiple display lines are separated with | characters.
        // Those details need to be explained to users
        $this->add_static_text('instructions', '', get_string('cardtextinstructions', 'minilessonitem_cards'));
        $this->add_sentenceprompt(1, get_string('cardtext', 'minilessonitem_cards'), true);

        // The image shown on the card (left aligned)
        $label = get_string('cardimage', 'minilessonitem_cards');
        $this->add_sentenceimage(1, $label, false);

        // Say the prompt - audio only
        $mform->addElement(
            'advcheckbox',
            constants::READSENTENCE,
            get_string('readcardtext', 'minilessonitem_cards'),
            get_string('readcardtext_desc', 'minilessonitem_cards'),
            [],
            [0, 1]
        );

        $this->add_ttsaudioselect(
            constants::POLLYVOICE,
            get_string('choosevoice', constants::M_COMPONENT),
            constants::READSENTENCE,
            0
        );

        $nossml = true;
        $this->add_voiceoptions(
            constants::POLLYOPTION,
            get_string('choosevoiceoption', constants::M_COMPONENT),
            constants::READSENTENCE,
            0,
            $nossml
        );
        $label = get_string('cardaudio', 'minilessonitem_cards');
        $this->add_sentenceaudio(1, $label, false);
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));

    }

}
