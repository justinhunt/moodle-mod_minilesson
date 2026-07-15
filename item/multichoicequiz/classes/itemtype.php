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

namespace minilessonitem_multichoicequiz;

use mod_minilesson\local\itemtype\item;
use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a multichoice quiz item in a minilesson activity.
 *
 * A multichoice quiz shows a set of up to MAXQUESTIONS multichoice questions,
 * one at a time, against a single shared resource (e.g. a reading passage held
 * in the standard item text / media prompts).
 *
 * @package    minilessonitem_multichoicequiz
 * @copyright  2026 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_READING, constants::SKILL_LISTENING];

    /** @var int The maximum number of questions in one quiz item. */
    public const MAXQUESTIONS = 5;

    /** @var string Shared setting: shuffle the answer options of every question. */
    public const SHUFFLEANSWER = 'customint1';

    /** @var string Shared setting: on a wrong answer, let the student keep choosing until correct. */
    public const ALLOWRETRY = 'customint2';

    /**
     * DB column holding the question text of the given question.
     *
     * Questions 1-4 use customtext1 .. customtext4. Question 5 uses customtext6,
     * because customtext5 is conventionally pollyvoice.
     *
     * @param int $questionnumber 1-based question number
     * @return string
     */
    public static function col_questiontext(int $questionnumber): string {
        return $questionnumber <= 4 ? 'customtext' . $questionnumber : 'customtext6';
    }

    /**
     * DB column holding the answer options (one per line) of the given question.
     *
     * Questions 1-4 use customdata1 .. customdata4. Question 5 uses customtext7,
     * because customdata5 is conventionally the media iframe.
     *
     * @param int $questionnumber 1-based question number
     * @return string
     */
    public static function col_answers(int $questionnumber): string {
        return $questionnumber <= 4 ? 'customdata' . $questionnumber : 'customtext7';
    }

    /**
     * DB column holding the correct answer (1 - MAXANSWERS) of the given question.
     *
     * Correct answers are stored in customint5 .. customint9, leaving
     * customint3 (confirmchoice) and customint4 (pollyoption) to their
     * conventional uses.
     *
     * @param int $questionnumber 1-based question number
     * @return string
     */
    public static function col_correctanswer(int $questionnumber): string {
        return 'customint' . (4 + $questionnumber);
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return stdClass
     */
    public function export_for_template(\renderer_base $output) {
        $itemrecord = $this->itemrecord;
        $testitem = parent::export_for_template($output);
        $testitem = $this->set_layout($testitem);

        // Shared settings, applied to all questions in the quiz.
        $testitem->confirmchoice = $itemrecord->{constants::CONFIRMCHOICE} ? 1 : 0;
        $testitem->shuffleanswers = !empty($itemrecord->{self::SHUFFLEANSWER});
        $testitem->allowretry = !empty($itemrecord->{self::ALLOWRETRY});

        // The questions.
        $testitem->questions = [];
        for ($qnumber = 1; $qnumber <= self::MAXQUESTIONS; $qnumber++) {
            $questiontext = utils::super_trim($itemrecord->{self::col_questiontext($qnumber)} ?? '');
            $answersraw = $itemrecord->{self::col_answers($qnumber)} ?? '';
            $answers = array_values(array_filter(array_map(function ($sentence) {
                return utils::super_trim($sentence);
            }, explode(PHP_EOL, $answersraw)), function ($sentence) {
                return $sentence !== '';
            }));
            if ($questiontext === '' || count($answers) === 0) {
                continue;
            }

            $question = new stdClass();
            $question->qindex = count($testitem->questions);
            $question->qnumber = $question->qindex + 1;
            $question->questiontext = $questiontext;
            $question->correctanswer = (int)($itemrecord->{self::col_correctanswer($qnumber)} ?? 1);
            $question->sentences = [];
            foreach ($answers as $aindex => $sentence) {
                $s = new stdClass();
                $s->index = $aindex;
                $s->indexplusone = $aindex + 1;
                $s->sentence = $sentence;
                $question->sentences[] = $s;
            }
            $testitem->questions[] = $question;
        }
        $testitem->totalquestions = count($testitem->questions);

        return $testitem;
    }

    /**
     * Validate an imported item record.
     *
     * @param stdClass $newrecord the record built from the import data
     * @param stdClass $cm the course module
     * @return stdClass|false an error object (col, message), or false if there is no error
     */
    public static function validate_import($newrecord, $cm) {
        $error = new stdClass();
        $error->col = '';
        $error->message = '';

        if (utils::super_trim($newrecord->{self::col_questiontext(1)}) == '') {
            $error->col = self::col_questiontext(1);
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }
        if (utils::super_trim($newrecord->{self::col_answers(1)}) == '') {
            $error->col = self::col_answers(1);
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        // Return false to indicate no error.
        return false;
    }

    /**
     * This is for use with importing, telling import class each column's id, db col name
     * and minilesson specific data type.
     *
     * @return array key columns
     */
    public static function get_keycolumns() {
        // Get the basic key columns and customize a little for instances of this item type.
        $keycols = parent::get_keycolumns();
        for ($qnumber = 1; $qnumber <= self::MAXQUESTIONS; $qnumber++) {
            $optional = $qnumber > 1;
            $questioncol = self::col_questiontext($qnumber);
            $answerscol = self::col_answers($qnumber);
            $correctanswercol = self::col_correctanswer($qnumber);
            // The key is the column name without the 'custom' prefix, e.g. customtext1 => text1.
            $keycols[substr($questioncol, 6)] = ['jsonname' => 'question' . $qnumber, 'type' => 'string',
                'optional' => $optional, 'default' => '', 'dbname' => $questioncol];
            $keycols[substr($answerscol, 6)] = ['jsonname' => 'answers' . $qnumber, 'type' => 'stringarray',
                'optional' => $optional, 'default' => [], 'dbname' => $answerscol];
            $keycols[substr($correctanswercol, 6)] = ['jsonname' => 'correctanswer' . $qnumber, 'type' => 'int',
                'optional' => $optional, 'default' => 1, 'dbname' => $correctanswercol];
        }
        $keycols['int1'] = ['jsonname' => 'shuffleanswer', 'type' => 'boolean',
            'optional' => true, 'default' => 0, 'dbname' => self::SHUFFLEANSWER];
        $keycols['int2'] = ['jsonname' => 'allowretry', 'type' => 'boolean',
            'optional' => true, 'default' => 0, 'dbname' => self::ALLOWRETRY];
        $keycols['int3'] = ['jsonname' => 'confirmchoice', 'type' => 'boolean',
            'optional' => true, 'default' => 0, 'dbname' => constants::CONFIRMCHOICE];
        return $keycols;
    }

    /**
     * This function returns the prompt that the AI generate method requires for multichoice quiz.
     *
     * @param array $itemtemplate the item template
     * @param string $generatemethod one of 'extract', 'reuse' or 'generate'
     * @return string the prompt
     */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        $shape = "For each question N (N = 1 to 5) return the question text as 'questionN', ";
        $shape .= "a one dimensional array of 4 answers as 'answersN', ";
        $shape .= "and the correct answer as a number 1-4 in 'correctanswerN'. ";

        switch ($generatemethod) {
            case 'extract':
                $prompt = "Create 5 multichoice questions in {language} suitable for {level} level learners ";
                $prompt .= "to test the learner's understanding of the following passage: [{text}] ";
                $prompt .= $shape;
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Create 5 multichoice questions in {language} suitable for {level} level learners ";
                $prompt .= "on the topic of: [{topic}] ";
                $prompt .= $shape;
                break;
        }
        return $prompt;
    }

    /**
     * Shape the item review display of an attempt: list each question with its correct answer.
     *
     * @param stdClass $result the result object to fill
     * @param stdClass $itemquizdata the exported item data
     * @return void
     */
    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $result->hasanswerdetails = false;
        $result->hasincorrectanswer = false;
        $result->incorrectans = [];

        $correctanswers = [];
        foreach ($itemquizdata->questions as $question) {
            foreach ($question->sentences as $sentence) {
                if ($question->correctanswer == $sentence->indexplusone) {
                    $correctanswers[] = ['sentence' => $question->questiontext . ' : ' . $sentence->sentence];
                    break;
                }
            }
        }
        $result->hascorrectanswer = count($correctanswers) > 0;
        $result->correctans = $correctanswers;
    }
}
