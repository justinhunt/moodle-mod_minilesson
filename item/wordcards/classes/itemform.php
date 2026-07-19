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
 * Word Cards mod_minilesson
 *
 * @package    minilessonitem_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace minilessonitem_wordcards;

use mod_minilesson\local\itemform\baseform;
use mod_minilesson\constants;
use mod_minilesson\utils;

/**
 * The authoring form for a wordcards item in a minilesson activity.
 *
 * @package    minilessonitem_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemform extends baseform {
    /**
     * Adds the item type specific fields to the form.
     *
     * @return void
     */
    public function custom_definition() {
        $mform = $this->_form;
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('wordcards_instructions1', 'minilessonitem_wordcards'));
        $this->add_itemsettings_heading();

        $this->add_dropdown(
            itemtype::ACTIVITYTYPE,
            get_string('activitytype', 'minilessonitem_wordcards'),
            [
                itemtype::MODE_LISTENTYPE => get_string('listenandtype', 'minilessonitem_wordcards'),
                itemtype::MODE_LISTENCHOOSE => get_string('listenandchoose', 'minilessonitem_wordcards'),
                itemtype::MODE_TYPEWORD => get_string('typetheword', 'minilessonitem_wordcards'),
                itemtype::MODE_CHOOSEWORD => get_string('choosetheword', 'minilessonitem_wordcards'),
            ],
            itemtype::MODE_LISTENTYPE
        );

        $this->add_ttsaudioselect(constants::POLLYVOICE, get_string('choosevoice', constants::M_COMPONENT));

        $nossml = true;
        $hideiffield = false;
        $hideifvalue = false;
        $this->add_voiceoptions(
            constants::POLLYOPTION,
            get_string('choosevoiceoption', constants::M_COMPONENT),
            $hideiffield,
            $hideifvalue,
            $nossml
        );

        $this->add_static_text('instructions', '', get_string('enterwordcardsitems', 'minilessonitem_wordcards'));
        $this->add_sentenceprompt(1, get_string('wordcardsitems', 'minilessonitem_wordcards'), true);

        $this->add_sentenceimage(1, null, false);
        $this->add_sentenceaudio(1, null, false);
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
        $this->add_hidestartpage(constants::GAPFILLHIDESTARTPAGE, get_string('hidestartpage_desc', constants::M_COMPONENT));
    }

    /**
     * Validate the submitted item settings.
     *
     * @param array $data the submitted form data
     * @param array $files the submitted files
     * @return array of element name => error message
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $textanswer = constants::TEXTANSWER . '1';
        $lines = explode(PHP_EOL, (string)($data[$textanswer] ?? ''));
        $lines = array_values(array_filter(array_map([utils::class, 'super_trim'], $lines)));
        if (count($lines) < 2) {
            $errors[$textanswer] = get_string('error:atleasttwowords', 'minilessonitem_wordcards');
            return $errors;
        }
        foreach ($lines as $i => $line) {
            $parts = explode('|', $line, 2);
            if (utils::super_trim($parts[0]) === '' || count($parts) < 2 || utils::super_trim($parts[1]) === '') {
                $errors[$textanswer] = get_string('error:badwordline', 'minilessonitem_wordcards', $i + 1);
                break;
            }
        }
        return $errors;
    }
}
