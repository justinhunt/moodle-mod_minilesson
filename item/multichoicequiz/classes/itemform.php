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
 * Authoring form for the multichoice quiz item type.
 *
 * @package    minilessonitem_multichoicequiz
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace minilessonitem_multichoicequiz;

use mod_minilesson\local\itemform\baseform;
use mod_minilesson\constants;

/**
 * Authoring form for the multichoice quiz item type.
 *
 * Up to itemtype::MAXQUESTIONS questions, each with a question text, a set of
 * answers (one per line) and a correct answer. Shuffle answers, confirm choice
 * and allow retry are single settings shared by all the questions.
 *
 * @package    minilessonitem_multichoicequiz
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemform extends baseform {
    /**
     * Add the item type specific form fields.
     *
     * @return void
     */
    public function custom_definition() {
        $mform = $this->_form;
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('multichoicequiz_instructions1', self::fetch_component()));

        // Settings shared by all the questions in the quiz.
        $this->add_itemsettings_heading();
        $this->add_checkbox(
            itemtype::SHOWALLQUESTIONS,
            get_string('showallquestions', self::fetch_component()),
            get_string('showallquestions_details', self::fetch_component())
        );
        $this->add_confirmchoice(constants::CONFIRMCHOICE, get_string('confirmchoice_formlabel', constants::M_COMPONENT));
        // With all the questions on the page at once, a tap is the answer - confirm choice does not apply.
        $mform->disabledIf(constants::CONFIRMCHOICE, itemtype::SHOWALLQUESTIONS, 'checked');
        $this->add_checkbox(itemtype::SHUFFLEANSWER, get_string('shuffleanswer', constants::M_COMPONENT));
        $this->add_allowretry(itemtype::ALLOWRETRY, get_string('allowretry_details', self::fetch_component()));
        $hideansweroptions = [
            itemtype::HIDEANSWER_NO => get_string('no'),
            itemtype::HIDEANSWER_ABCD => get_string('hideanswer_abcd', constants::M_COMPONENT),
        ];
        $this->add_dropdown(
            itemtype::HIDEANSWERTEXT,
            get_string('hideanswertext', constants::M_COMPONENT),
            $hideansweroptions,
            itemtype::HIDEANSWER_NO
        );
        $this->add_static_text('instructionshideanswertext', '', get_string('hideanswertext_detail', self::fetch_component()));
        // Shuffling is suppressed in A,B,C,D mode (the letters must keep the author's order).
        $mform->disabledIf(itemtype::SHUFFLEANSWER, itemtype::HIDEANSWERTEXT, 'eq', itemtype::HIDEANSWER_ABCD);
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));

        // The questions. Only question 1 is required.
        $correctanswernumbers = range(1, constants::MAXANSWERS);
        $correctansweroptions = array_combine($correctanswernumbers, $correctanswernumbers);
        for ($qnumber = 1; $qnumber <= itemtype::MAXQUESTIONS; $qnumber++) {
            $required = $qnumber === 1;
            $mform->addElement('header', 'questionheading' . $qnumber, get_string('questionno', self::fetch_component(), $qnumber));
            $mform->setExpanded('questionheading' . $qnumber, $required);

            $questionfield = itemtype::col_questiontext($qnumber);
            $this->add_textarearesponse($questionfield, get_string('questiontext', self::fetch_component()), $required);
            $mform->setType($questionfield, PARAM_RAW);

            $answersfield = itemtype::col_answers($qnumber);
            $this->add_sentenceprompt($answersfield, get_string('questionanswers', self::fetch_component()), $required);

            $this->add_dropdown(
                itemtype::col_correctanswer($qnumber),
                get_string('correctanswer', constants::M_COMPONENT),
                $correctansweroptions,
                1
            );
        }
    }

    /**
     * Frankenstyle component name of this subplugin (for get_string calls).
     *
     * @return string
     */
    protected static function fetch_component(): string {
        return 'minilessonitem_' . constants::TYPE_MULTICHOICEQUIZ;
    }

    /**
     * Make sure each filled-in question has answers and a valid correct answer.
     *
     * @param array $data form data
     * @param array $files form files
     * @return array errors, keyed by form element name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        for ($qnumber = 1; $qnumber <= itemtype::MAXQUESTIONS; $qnumber++) {
            $questiontext = trim($data[itemtype::col_questiontext($qnumber)] ?? '');
            $answersraw = trim($data[itemtype::col_answers($qnumber)] ?? '');
            if ($questiontext === '' && $answersraw === '') {
                continue;
            }
            if ($questiontext === '') {
                $errors[itemtype::col_questiontext($qnumber)] = get_string('required');
                continue;
            }
            $answers = array_filter(array_map('trim', explode(PHP_EOL, $answersraw)), function ($answer) {
                return $answer !== '';
            });
            if (count($answers) < 2) {
                $errors[itemtype::col_answers($qnumber)] = get_string('error:needtwoanswers', self::fetch_component());
                continue;
            }
            $correctanswer = (int)($data[itemtype::col_correctanswer($qnumber)] ?? 1);
            if ($correctanswer > count($answers)) {
                $errors[itemtype::col_correctanswer($qnumber)] = get_string('error:correctanswertoohigh', self::fetch_component());
            }
        }
        return $errors;
    }
}
