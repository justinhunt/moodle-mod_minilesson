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

    /** @var string Shared setting: hide the answer text of every question (see the HIDEANSWER_* values). */
    public const HIDEANSWERTEXT = 'customint10';

    /** @var string Shared setting: show all the questions on the page at once, instead of one at a time. */
    public const SHOWALLQUESTIONS = 'customint11';

    /** @var int Show the answer text (default). */
    public const HIDEANSWER_NO = 0;

    /** @var int Hide the answer text completely. */
    public const HIDEANSWER_YES = 1;

    /** @var int Hide the answer text, showing A, B, C or D in its place (for audio-only answer options). */
    public const HIDEANSWER_ABCD = 2;

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
        $testitem->showallquestions = !empty($itemrecord->{self::SHOWALLQUESTIONS});
        // When all the questions are on the page at once, a tap is the answer - there is
        // no meaningful choice to confirm, so confirm choice is suppressed.
        $testitem->confirmchoice = $itemrecord->{constants::CONFIRMCHOICE} && !$testitem->showallquestions ? 1 : 0;
        $testitem->allowretry = !empty($itemrecord->{self::ALLOWRETRY});
        $testitem->hideanswertext = (int)($itemrecord->{self::HIDEANSWERTEXT} ?? self::HIDEANSWER_NO);
        // In A,B,C,D mode the on-screen letters must keep the order the author (and any audio) gave them,
        // so shuffling is suppressed.
        $testitem->shuffleanswers = !empty($itemrecord->{self::SHUFFLEANSWER})
            && $testitem->hideanswertext != self::HIDEANSWER_ABCD;

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
                // The prompt is what is shown on the answer button; the resulttext is what
                // is shown on the results screens.
                switch ($testitem->hideanswertext) {
                    case self::HIDEANSWER_YES:
                        $s->prompt = '';
                        $s->resulttext = $sentence;
                        break;
                    case self::HIDEANSWER_ABCD:
                        $s->prompt = chr(65 + $aindex);
                        $s->resulttext = $s->prompt . '. ' . $sentence;
                        break;
                    case self::HIDEANSWER_NO:
                    default:
                        $s->prompt = $sentence;
                        $s->resulttext = $sentence;
                        break;
                }
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

        // Each filled-in question needs at least two answers and a correct answer within range
        // (mirrors the authoring form validation).
        $component = 'minilessonitem_' . constants::TYPE_MULTICHOICEQUIZ;
        for ($qnumber = 1; $qnumber <= self::MAXQUESTIONS; $qnumber++) {
            $questiontext = utils::super_trim($newrecord->{self::col_questiontext($qnumber)} ?? '');
            $answersraw = utils::super_trim($newrecord->{self::col_answers($qnumber)} ?? '');
            if ($questiontext === '' && $answersraw === '') {
                continue;
            }
            if ($questiontext === '') {
                $error->col = self::col_questiontext($qnumber);
                $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
                return $error;
            }
            $answers = array_filter(array_map('trim', explode(PHP_EOL, $answersraw)), function ($answer) {
                return $answer !== '';
            });
            if (count($answers) < 2) {
                $error->col = self::col_answers($qnumber);
                $error->message = get_string('error:needtwoanswers', $component);
                return $error;
            }
            $correctanswer = (int) ($newrecord->{self::col_correctanswer($qnumber)} ?? 1);
            if ($correctanswer < 1 || $correctanswer > count($answers)) {
                $error->col = self::col_correctanswer($qnumber);
                $error->message = get_string('error:correctanswertoohigh', $component);
                return $error;
            }
        }

        // Option value check for hideanswertext: reject impossible values.
        $allowedhide = [self::HIDEANSWER_NO, self::HIDEANSWER_YES, self::HIDEANSWER_ABCD];
        if (isset($newrecord->{self::HIDEANSWERTEXT}) && !in_array((int) $newrecord->{self::HIDEANSWERTEXT}, $allowedhide)) {
            $error->col = self::HIDEANSWERTEXT;
            $error->message = get_string(
                'error:invalidoptionvalue',
                constants::M_COMPONENT,
                ['value' => $newrecord->{self::HIDEANSWERTEXT}, 'allowed' => implode(',', $allowedhide)]
            );
            return $error;
        }

        // Return false to indicate no error.
        return false;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'A set of up to ' . self::MAXQUESTIONS . ' multichoice questions about one shared resource, '
            . 'e.g. a reading passage or a listening passage/dialog. The resource stays on screen while the '
            . 'questions are asked one at a time (or all at once). Use it for reading or listening comprehension '
            . 'quizzes where several questions share the same stimulus, instead of creating several separate '
            . 'multichoice items. Answer options are text only - if you need image or audio answer options, '
            . 'or per-question feedback, use separate multichoice items instead. For more than '
            . self::MAXQUESTIONS . ' questions, split into two multichoicequiz items.';
    }

    /**
     * The agent-facing import field spec for multichoicequiz. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions', 'text',
            'textarea', 'tts', 'ttsvoice', 'ttsoption', 'ttsautoplay',
            'ttsdialog', 'ttsdialogvoicea', 'ttsdialogvoiceb', 'ttsdialogvoicec',
            'ttsdialoglabela', 'ttsdialoglabelb', 'ttsdialoglabelc', 'ttsdialogvisible',
            'ttspassage', 'ttspassagevoice', 'ttspassagespeed',
            'ytid', 'ytstart', 'ytend', 'timelimit', 'layout']);
        $fields['type']['example'] = 'multichoicequiz';
        $fields['text']['description'] = 'Optional short heading-level text shown above the activity. '
            . 'Put the reading passage itself in "textarea", not here.';
        $fields['textarea']['description'] = 'The shared resource the questions are about, e.g. the reading '
            . 'passage, as HTML or plain text. For a listening quiz leave this empty and deliver the passage '
            . 'with ttsdialog or ttspassage instead.';

        // The per-question fields.
        $ownfields = [];
        for ($qnumber = 1; $qnumber <= self::MAXQUESTIONS; $qnumber++) {
            $qualifier = $qnumber === 1 ? '' : ' Omit for a shorter quiz.';
            $ownfields['question' . $qnumber] = [
                'description' => 'The text of question ' . $qnumber . '.' . $qualifier,
            ];
            $ownfields['answers' . $qnumber] = [
                'description' => 'The answer options for question ' . $qnumber . ', as an array of 2 to '
                    . constants::MAXANSWERS . ' strings.',
            ];
            $ownfields['correctanswer' . $qnumber] = [
                'description' => 'The 1-based index of the correct answer option for question ' . $qnumber . '.',
            ];
        }
        $ownfields['question1']['example'] = 'What does the boy do with the two cans at the end?';
        $ownfields['answers1']['example'] = '["He drinks them.", "He stands on them.", "He throws them away."]';
        $ownfields['correctanswer1']['example'] = '2';

        // The shared settings.
        $ownfields += [
            'showallquestions' => [
                'description' => 'Show all the questions on the page at the same time, instead of one at a time. '
                    . 'Each question is still checked as soon as it is answered; confirmchoice does not apply '
                    . 'in this mode.',
                'options' => [
                    ['value' => '0', 'meaning' => 'One question at a time (default)'],
                    ['value' => '1', 'meaning' => 'All questions on the page at once'],
                ],
            ],
            'confirmchoice' => [
                'description' => 'Whether the learner must confirm each answer choice before it is checked.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Answers are checked on tap (default)'],
                    ['value' => '1', 'meaning' => 'The learner must confirm their choice'],
                ],
            ],
            'shuffleanswer' => [
                'description' => 'Whether the display order of the answer options is shuffled, in every question. '
                    . 'Ignored (never shuffled) when hideanswertext=2, so that the A,B,C,D labels keep the '
                    . 'authored order.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Keep the authored order (default)'],
                    ['value' => '1', 'meaning' => 'Shuffle the answer options'],
                ],
            ],
            'allowretry' => [
                'description' => 'On a wrong answer, allow the learner to keep choosing until they find the '
                    . 'correct answer. The question is still marked incorrect.',
                'options' => [
                    ['value' => '0', 'meaning' => 'One attempt per question (default)'],
                    ['value' => '1', 'meaning' => 'Keep choosing until correct'],
                ],
            ],
            'hideanswertext' => [
                'description' => 'Controls display of the answer option text, in every question.',
                'options' => [
                    ['value' => (string) self::HIDEANSWER_NO,
                        'meaning' => 'Show the answer text (default)'],
                    ['value' => (string) self::HIDEANSWER_ABCD,
                        'meaning' => 'Show the labels A,B,C,D in place of the answer text - for listening quizzes '
                            . 'where the audio reads the options and refers to them by letter. The answer text '
                            . 'still shows on the results screens, and shuffleanswer is ignored'],
                ],
            ],
        ];
        foreach ($ownfields as $jsonname => $overlay) {
            $fields[$jsonname] = static::aigen_seed_field_spec($jsonname, $overlay);
        }

        $fields['filesid'] = [
            'jsonname' => 'filesid',
            'type' => 'int',
            'required' => false,
            'default' => '',
            'description' => 'Links this item to its entry in the top level "files" object of the payload. '
                . 'Only needed when the item has an uploaded media resource.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per quiz. Supply the shared resource (a reading passage in '
                . '"textarea", or a listening passage/conversation via ttsdialog or ttspassage), then '
                . 'question1/answers1/correctanswer1 (required) and up to ' . (self::MAXQUESTIONS - 1)
                . ' more numbered question/answers/correctanswer sets. Every filled-in question needs at least '
                . '2 answer options and a correctanswer within range. For a listening quiz where the audio reads '
                . 'the answer options, set hideanswertext=2 and start the spoken options with "A)", "B)", "C)", "D)".',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::MEDIAQUESTION,
                    'description' => 'An uploaded image, audio or video used as the shared resource.',
                    'filenames' => 'Any filename; the extension decides how it renders: '
                        . 'image (png/jpg/gif/svg), video (mp4/mov/webm) or audio (mp3/m4a/ogg/wav).',
                ],
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'multichoicequiz',
                        'name' => 'Reading quiz',
                        'instructions' => 'Read the passage and answer the questions.',
                        'textarea' => '<p>A big man jogs to his car and leans on it to rest. A young man sees him '
                            . 'and misunderstands - he thinks the big man is pushing the car, so he helps push. '
                            . 'The car falls off the cliff.</p>',
                        'question1' => 'Why does the big man lean on the car?',
                        'answers1' => ['To push it.', 'To rest.', 'To open the door.'],
                        'correctanswer1' => 2,
                        'question2' => 'What does the young man think is happening?',
                        'answers2' => [
                            'The big man is pushing the car.',
                            'The big man is stealing the car.',
                            'The big man is washing the car.',
                        ],
                        'correctanswer2' => 1,
                        'shuffleanswer' => 1,
                        'allowretry' => 0,
                    ],
                ],
            ],
        ];
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
        $keycols['int10'] = ['jsonname' => 'hideanswertext', 'type' => 'int',
            'optional' => true, 'default' => self::HIDEANSWER_NO, 'dbname' => self::HIDEANSWERTEXT];
        $keycols['int11'] = ['jsonname' => 'showallquestions', 'type' => 'boolean',
            'optional' => true, 'default' => 0, 'dbname' => self::SHOWALLQUESTIONS];
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
                    $correctanswers[] = ['sentence' => $question->questiontext . ' : ' . $sentence->resulttext];
                    break;
                }
            }
        }
        $result->hascorrectanswer = count($correctanswers) > 0;
        $result->correctans = $correctanswers;
    }
}
