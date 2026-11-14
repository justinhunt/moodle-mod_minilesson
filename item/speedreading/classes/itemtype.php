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

namespace minilessonitem_speedreading;

use mod_minilesson\local\itemtype\item;
use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a speed reading item in a minilesson activity.
 *
 * The student sees four screens: an introduction, the reading passage (timed), a set of up to
 * MAXQUESTIONS multichoice questions about it, and the results. The results show the reading speed
 * in words per minute, speed points out of 10, and the quiz score. The speed only counts when
 * enough questions are right (the pass mark). The grade is the quiz score.
 *
 * @package    minilessonitem_speedreading
 * @copyright  2026 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_READING];

    /** @var int The maximum number of questions in one item. */
    public const MAXQUESTIONS = 5;

    /*
     * The per question columns are the same as in the multichoice quiz item, so the two item
     * types share one import format for their questions:
     *
     *   question 1 : customtext1  customdata1  customint5   customtext8
     *   question 2 : customtext2  customdata2  customint6   customtext9
     *   question 3 : customtext3  customdata3  customint7   customtext10
     *   question 4 : customtext4  customdata4  customint8   customtext11
     *   question 5 : customtext6  customtext7  customint9   customtext12
     *                (text)       (answers)    (correct)    (feedback)
     *
     * The reading passage is the standard item text area (itemtextarea). The introduction screen
     * uses the standard title, instructions, item text and media (picture) fields.
     */

    /** @var string Shuffle the answer options of every question. */
    public const SHUFFLEANSWER = 'customint1';

    /** @var string Do not grade this item (0 = graded, the default). */
    public const NOGRADE = 'customint10';

    /** @var string Show all the questions on the page at once, instead of one at a time. */
    public const SHOWALLQUESTIONS = 'customint11';

    /** @var string Show the timer while the student reads. */
    public const SHOWTIMER = 'customint12';

    /** @var string The percentage of questions that must be right for the reading speed to count. */
    public const PASSMARK = 'customint13';

    /** @var string The reading speed (words or characters per minute) that gets full speed points. */
    public const TARGETWPM = 'customint14';

    /** @var string The number of words in the passage, when the teacher sets it (0 = count automatically). */
    public const WORDCOUNT = 'customint15';

    /** @var int The default pass mark, in percent. */
    public const DEFAULT_PASSMARK = 60;

    /** @var int The default target speed, in words per minute. */
    public const DEFAULT_TARGETWPM = 200;

    /** @var int The default target speed for languages counted in characters, in characters per minute. */
    public const DEFAULT_TARGETCPM = 400;

    /** @var int The most speed points a student can get. */
    public const MAXSPEEDPOINTS = 10;

    /** @var int Faster than this many times the target speed is taken as not really reading. */
    public const TOOFASTFACTOR = 5;

    /**
     * @var string[] Languages whose reading speed is measured in characters, not words. Most do not put spaces
     * between words. Korean does, but its speed and text length are usually given in syllables (one character each).
     */
    public const CHARACTER_LANGS = ['ja', 'zh', 'ko', 'th', 'lo', 'km', 'my', 'yu'];

    /**
     * DB column holding the question text of the given question.
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
     * @param int $questionnumber 1-based question number
     * @return string
     */
    public static function col_answers(int $questionnumber): string {
        return $questionnumber <= 4 ? 'customdata' . $questionnumber : 'customtext7';
    }

    /**
     * DB column holding the correct answer (1 - MAXANSWERS) of the given question.
     *
     * @param int $questionnumber 1-based question number
     * @return string
     */
    public static function col_correctanswer(int $questionnumber): string {
        return 'customint' . (4 + $questionnumber);
    }

    /**
     * DB column holding the optional feedback of the given question, shown on the results screen.
     *
     * @param int $questionnumber 1-based question number
     * @return string
     */
    public static function col_feedback(int $questionnumber): string {
        return 'customtext' . (7 + $questionnumber);
    }

    /**
     * The splash screen (the introduction) and the activity get the boxed card styling.
     *
     * @return bool
     */
    public function uses_boxed_layout() {
        return true;
    }

    /**
     * This item type shows its own results page, so on the lesson's last item the next button
     * says "Finish" only there.
     *
     * @return bool
     */
    public function has_results_page() {
        return true;
    }

    /**
     * The question feedback on the results screen may be translated. The passage is never offered
     * for translation, because that would undo the reading exercise.
     *
     * @return bool
     */
    public static function supports_translation() {
        return true;
    }

    /**
     * The answer options of every question stay in the language of the lesson when feedback
     * is translated.
     *
     * @param stdClass $itemrecord The item's row from the minilesson_rsquestions table.
     * @return string[] The answer options.
     */
    public static function get_translation_keepterms(stdClass $itemrecord) {
        $keepterms = [];
        for ($qnumber = 1; $qnumber <= self::MAXQUESTIONS; $qnumber++) {
            $answers = explode(PHP_EOL, $itemrecord->{self::col_answers($qnumber)} ?? '');
            foreach ($answers as $answer) {
                $answer = utils::super_trim($answer);
                if ($answer !== '') {
                    $keepterms[] = $answer;
                }
            }
        }
        return array_values(array_unique($keepterms));
    }

    /**
     * Whether reading speed is measured in characters per minute for this language, because it does
     * not put spaces between words.
     *
     * @param string $language the lesson language, e.g. ja-JP
     * @return bool
     */
    public static function counts_characters(string $language): bool {
        return in_array(utils::fetch_short_lang($language), self::CHARACTER_LANGS);
    }

    /**
     * The default target speed for a language: words per minute, or characters per minute.
     *
     * @param string $language the lesson language, e.g. en-US
     * @return int
     */
    public static function default_target(string $language): int {
        return self::counts_characters($language) ? self::DEFAULT_TARGETCPM : self::DEFAULT_TARGETWPM;
    }

    /**
     * Count the words (or, for languages without spaces, the characters) in a passage.
     *
     * Keep in step with count_words() in amd/src/formhelper.js, which shows the count live in the
     * authoring form.
     *
     * @param string $html the passage, as HTML or plain text
     * @param string $language the lesson language, e.g. en-US
     * @return int
     */
    public static function count_words(string $html, string $language): int {
        $text = self::translatable_plaintext($html);
        if (self::counts_characters($language)) {
            return (int) preg_match_all('/[\p{L}\p{N}]/u', $text);
        }
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, function ($word) {
            return preg_match('/[\p{L}\p{N}]/u', $word) === 1;
        });
        return count($words);
    }

    /**
     * The number of questions a student must get right for their reading speed to count.
     *
     * @param int $totalquestions the number of questions
     * @param int $passmark the pass mark, in percent
     * @return int
     */
    public static function required_correct(int $totalquestions, int $passmark): int {
        // Integer maths, so 60% of 5 is exactly 3.
        return intdiv($totalquestions * $passmark + 99, 100);
    }

    /**
     * Speed points out of 10, following the speed reading conversion chart: the target speed or
     * faster gets 10, half the target speed or slower gets 0, and it is a straight line in between.
     *
     * Keep in step with speed_points() in amd/src/itemtype.js.
     *
     * @param int $rate the reading speed (words or characters per minute)
     * @param int $target the target speed
     * @return int
     */
    public static function speed_points(int $rate, int $target): int {
        if ($target <= 0) {
            return 0;
        }
        $half = $target / 2;
        $points = (int) round(self::MAXSPEEDPOINTS * ($rate - $half) / $half);
        return max(0, min(self::MAXSPEEDPOINTS, $points));
    }

    /**
     * Read an int setting, using the default when it has never been set.
     *
     * @param stdClass $itemrecord the item record
     * @param string $column the column name
     * @param int $default the default value
     * @return int
     */
    protected static function int_setting(stdClass $itemrecord, string $column, int $default): int {
        $value = $itemrecord->{$column} ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        return (int) $value;
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

        // The standard title, instructions, item text and picture belong on the introduction screen,
        // and the item text area is the reading passage. Take them off the standard item header, which
        // would show them above the passage and the questions.
        $testitem->introtitle = $itemrecord->name;
        $testitem->introinstructions = $testitem->iteminstructions ?? '';
        $testitem->introtext = $testitem->itemtext ?? '';
        $testitem->introimage = $testitem->itemimage ?? '';
        $testitem->passage = $testitem->itemtextarea ?? '';
        // Audio of the passage, or any other media, would undo the reading exercise, so none is shown
        // even when an import supplied it.
        $hidden = ['title', 'iteminstructions', 'itemtext', 'itemimage', 'itemtextarea', 'itemvideo', 'itemaudio',
            'itemiframe', 'itemytvideoid', 'itemttsaudio', 'itemttsdialog', 'itemttspassage', 'audiostory',
            'itemnativelangchooser'];
        foreach ($hidden as $field) {
            unset($testitem->{$field});
        }

        // Settings.
        $testitem->showtimer = self::int_setting($itemrecord, self::SHOWTIMER, 1) ? true : false;
        $testitem->showallquestions = !empty($itemrecord->{self::SHOWALLQUESTIONS});
        $testitem->shuffleanswers = !empty($itemrecord->{self::SHUFFLEANSWER});
        $testitem->graded = empty($itemrecord->{self::NOGRADE});
        $testitem->passmark = max(0, min(100, self::int_setting($itemrecord, self::PASSMARK, self::DEFAULT_PASSMARK)));
        // Empty or 0 means the language's default (an import may leave the target out).
        $testitem->targetwpm = self::int_setting($itemrecord, self::TARGETWPM, 0);
        if ($testitem->targetwpm < 1) {
            $testitem->targetwpm = self::default_target((string) $this->moduleinstance->ttslanguage);
        }
        $testitem->toofastwpm = $testitem->targetwpm * self::TOOFASTFACTOR;
        $testitem->cpm = self::counts_characters((string) $this->moduleinstance->ttslanguage);
        $wordcount = (int) ($itemrecord->{self::WORDCOUNT} ?? 0);
        if ($wordcount <= 0) {
            $wordcount = self::count_words(
                (string) ($itemrecord->{constants::QUESTIONTEXTAREA} ?? ''),
                (string) $this->moduleinstance->ttslanguage
            );
        }
        $testitem->wordcount = $wordcount;

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
            $question->correctanswer = (int) ($itemrecord->{self::col_correctanswer($qnumber)} ?? 1);
            $question->correcttext = '';
            $question->feedback = trim($itemrecord->{self::col_feedback($qnumber)} ?? '');
            $this->add_translatetext_data($question, $question->feedback);
            $question->sentences = [];
            foreach ($answers as $aindex => $sentence) {
                $s = new stdClass();
                $s->index = $aindex;
                $s->indexplusone = $aindex + 1;
                $s->sentence = $sentence;
                if ($s->indexplusone === $question->correctanswer) {
                    $question->correcttext = $sentence;
                }
                $question->sentences[] = $s;
            }
            $testitem->questions[] = $question;
        }
        $testitem->totalquestions = count($testitem->questions);
        $testitem->needed = self::required_correct($testitem->totalquestions, $testitem->passmark);

        return $testitem;
    }

    /**
     * Build the data for the results template, from the item data and the stored results of an attempt.
     *
     * The results screen at the end of the item builds the same data in JS (build_results in
     * amd/src/itemtype.js). Keep the two in step.
     *
     * @param stdClass $itemquizdata the exported item data (see export_for_template)
     * @param stdClass $resultsdata the results data the item's JS stored with the step
     * @return stdClass
     */
    public static function build_results_context(stdClass $itemquizdata, stdClass $resultsdata): stdClass {
        $context = new stdClass();
        $context->finishedreading = !empty($resultsdata->finishedreading);
        $context->cpm = !empty($resultsdata->cpm);
        $context->rate = (int) ($resultsdata->rate ?? 0);
        $context->wordcount = (int) ($resultsdata->wordcount ?? 0);
        $readseconds = (int) ($resultsdata->readseconds ?? 0);
        $context->minutes = intdiv($readseconds, 60);
        $context->seconds = $readseconds % 60;
        $context->speedpoints = (int) ($resultsdata->speedpoints ?? 0);
        $context->speedpercent = $context->speedpoints * 10;
        $context->maxspeedpoints = self::MAXSPEEDPOINTS;
        $context->targetwpm = (int) ($resultsdata->targetwpm ?? self::DEFAULT_TARGETWPM);
        $context->speedcounts = !empty($resultsdata->speedcounts);
        $context->toofast = !empty($resultsdata->toofast);
        $context->belowpassmark = $context->finishedreading && !$context->speedcounts && !$context->toofast;
        $context->correctitems = (int) ($resultsdata->correctitems ?? 0);
        $context->totalitems = (int) ($resultsdata->totalitems ?? 0);
        $context->percent = $context->totalitems > 0 ? (int) round(100 * $context->correctitems / $context->totalitems) : 0;
        $context->needed = (int) ($resultsdata->needed ?? 0);

        $answers = isset($resultsdata->answers) && is_array($resultsdata->answers) ? $resultsdata->answers : [];
        $context->questions = [];
        foreach ($itemquizdata->questions as $question) {
            $given = (int) ($answers[$question->qindex] ?? 0);
            $row = new stdClass();
            $row->qnumber = $question->qnumber;
            $row->questiontext = $question->questiontext;
            $row->given = '';
            foreach ($question->sentences as $sentence) {
                if ($sentence->indexplusone === $given) {
                    $row->given = $sentence->sentence;
                }
            }
            $row->hasgiven = $given > 0;
            $row->correct = $given > 0 && $given === (int) $question->correctanswer;
            $row->wrong = !$row->correct;
            $row->correcttext = $question->correcttext;
            $row->feedback = $question->feedback;
            $translatefields = ['cantranslatetext', 'translatecmid', 'translateitemid', 'translatesourcelang',
                'translatedestlang', 'translatedir', 'translatertl', 'translatetext', 'translatemasked', 'translatephrases'];
            foreach ($translatefields as $field) {
                if (isset($question->{$field})) {
                    $row->{$field} = $question->{$field};
                }
            }
            $context->questions[] = $row;
        }
        $context->passage = $itemquizdata->passage ?? '';
        $context->contentdir = $itemquizdata->contentdir ?? 'ltr';
        $context->contentlang = $itemquizdata->contentlang ?? '';
        return $context;
    }

    /**
     * Shape the item review display of an attempt: the results screen of the item, again.
     *
     * @param stdClass $result the result object to fill
     * @param stdClass $itemquizdata the exported item data
     * @return void
     */
    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $result->hascorrectanswer = false;
        $result->hasincorrectanswer = false;
        $result->correctans = [];
        $result->incorrectans = [];
        if (empty($result->resultsdata) || !is_object($result->resultsdata)) {
            $result->hasanswerdetails = false;
            return;
        }
        $result->hasanswerdetails = true;
        $result->resultstemplate = self::get_component() . '/speedreadingresults';
        $result->resultsdatajson = json_encode(
            self::build_results_context($itemquizdata, $result->resultsdata),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
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
        $component = self::get_component();

        if (utils::super_trim(strip_tags($newrecord->{constants::QUESTIONTEXTAREA} ?? '')) === '') {
            $error->col = constants::QUESTIONTEXTAREA;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }
        if (utils::super_trim($newrecord->{self::col_questiontext(1)} ?? '') === '') {
            $error->col = self::col_questiontext(1);
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        // Each filled-in question needs at least two answers and a correct answer within range.
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

        $passmark = $newrecord->{self::PASSMARK} ?? null;
        if ($passmark !== null && $passmark !== '' && ((int) $passmark < 0 || (int) $passmark > 100)) {
            $error->col = self::PASSMARK;
            $error->message = get_string('error:passmark', $component);
            return $error;
        }
        $target = $newrecord->{self::TARGETWPM} ?? null;
        // 0 means the language's default.
        if ($target !== null && $target !== '' && (int) $target < 0) {
            $error->col = self::TARGETWPM;
            $error->message = get_string('error:targetwpm', $component);
            return $error;
        }

        return false;
    }

    /**
     * This is for use with importing, telling import class each column's id, db col name
     * and minilesson specific data type.
     *
     * @return array key columns
     */
    public static function get_keycolumns() {
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
        $keycols['int10'] = ['jsonname' => 'nograde', 'type' => 'boolean',
            'optional' => true, 'default' => 0, 'dbname' => self::NOGRADE];
        $keycols['int11'] = ['jsonname' => 'showallquestions', 'type' => 'boolean',
            'optional' => true, 'default' => 0, 'dbname' => self::SHOWALLQUESTIONS];
        $keycols['int12'] = ['jsonname' => 'showtimer', 'type' => 'boolean',
            'optional' => true, 'default' => 1, 'dbname' => self::SHOWTIMER];
        $keycols['int13'] = ['jsonname' => 'passmark', 'type' => 'int',
            'optional' => true, 'default' => self::DEFAULT_PASSMARK, 'dbname' => self::PASSMARK];
        $keycols['int14'] = ['jsonname' => 'targetwpm', 'type' => 'int',
            'optional' => true, 'default' => 0, 'dbname' => self::TARGETWPM];
        $keycols['int15'] = ['jsonname' => 'wordcount', 'type' => 'int',
            'optional' => true, 'default' => 0, 'dbname' => self::WORDCOUNT];
        for ($qnumber = 1; $qnumber <= self::MAXQUESTIONS; $qnumber++) {
            $feedbackcol = self::col_feedback($qnumber);
            $keycols[substr($feedbackcol, 6)] = ['jsonname' => 'feedback' . $qnumber, 'type' => 'string',
                'optional' => true, 'default' => '', 'dbname' => $feedbackcol];
        }
        return $keycols;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'Speed reading practice. The learner reads a passage silently while a timer runs, then answers up to '
            . self::MAXQUESTIONS . ' multichoice questions about it without seeing the passage again. The results show '
            . 'their reading speed (words per minute), speed points out of 10, and the quiz score. Use it to build '
            . 'reading fluency. The passage must be EASY for the learners: below their level, with almost no unknown '
            . 'words, because the aim is to read faster, not to learn new language. For careful comprehension of a harder '
            . 'text, use multichoicequiz instead.';
    }

    /**
     * The agent-facing import field spec for speedreading. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions', 'text', 'textarea']);
        $fields['type']['example'] = 'speedreading';
        $fields['name']['description'] = 'The title of the passage. Shown on the introduction screen and above the passage.';
        $fields['instructions']['description'] = 'One short line on the introduction screen, e.g. "Read the story as '
            . 'fast as you can. Then answer the questions."';
        $fields['text']['description'] = 'Optional tips on the introduction screen, as HTML or plain text. A note that the '
            . 'timer starts when the learner taps Start reading, and that they cannot go back to the passage, is added '
            . 'automatically, so do not write it.';
        $fields['textarea']['description'] = 'Required. The reading passage, as HTML or plain text. About 150 to 500 words of '
            . 'easy text.';

        $ownfields = [];
        for ($qnumber = 1; $qnumber <= self::MAXQUESTIONS; $qnumber++) {
            $qualifier = $qnumber === 1 ? '' : ' Omit for fewer questions.';
            $ownfields['question' . $qnumber] = [
                'description' => 'The text of question ' . $qnumber . ', about the passage.' . $qualifier,
            ];
            $ownfields['answers' . $qnumber] = [
                'description' => 'The answer options for question ' . $qnumber . ', as an array of 2 to '
                    . constants::MAXANSWERS . ' strings.',
            ];
            $ownfields['correctanswer' . $qnumber] = [
                'description' => 'The 1-based index of the correct answer option for question ' . $qnumber . '.',
            ];
            $ownfields['feedback' . $qnumber] = [
                'description' => 'Optional feedback for question ' . $qnumber . ', explaining why the correct answer is '
                    . 'correct. Shown on the results screen only.',
            ];
        }
        $ownfields['question1']['example'] = 'Where is the night market?';
        $ownfields['answers1']['example'] = '["In a park", "Behind Mia\'s school", "At the train station"]';
        $ownfields['correctanswer1']['example'] = '2';
        $ownfields['feedback1']['example'] = 'The first sentence says it is in the car park behind her school.';

        $ownfields += [
            'showallquestions' => [
                'description' => 'Show all the questions on one page with a Submit button, instead of one at a time.',
                'options' => [
                    ['value' => '0', 'meaning' => 'One question at a time; a tap moves on (default)'],
                    ['value' => '1', 'meaning' => 'All questions on one page, with a Submit button'],
                ],
            ],
            'shuffleanswer' => [
                'description' => 'Whether the display order of the answer options is shuffled, in every question.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Keep the authored order (default)'],
                    ['value' => '1', 'meaning' => 'Shuffle the answer options'],
                ],
            ],
            'showtimer' => [
                'description' => 'Whether the learner sees the timer while reading. The time is recorded either way.',
                'options' => [
                    ['value' => '1', 'meaning' => 'Show the timer (default)'],
                    ['value' => '0', 'meaning' => 'Hide the timer'],
                ],
            ],
            'passmark' => [
                'description' => 'The percentage of questions that must be right for the reading speed to count. '
                    . 'Default ' . self::DEFAULT_PASSMARK . '.',
            ],
            'targetwpm' => [
                'description' => 'The reading speed that gets the full 10 speed points; half of it gets 0. In words per '
                    . 'minute, or characters per minute for languages counted in characters ('
                    . implode(', ', self::CHARACTER_LANGS) . '). Leave out (0) for the default: '
                    . self::DEFAULT_TARGETWPM . ' words, or ' . self::DEFAULT_TARGETCPM . ' characters, per minute.',
            ],
            'wordcount' => [
                'description' => 'The number of words in the passage. Leave out (0) to count automatically.',
            ],
            'nograde' => [
                'description' => 'Whether the item is left out of the lesson grade. The grade is the quiz score.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Graded (default)'],
                    ['value' => '1', 'meaning' => 'Not graded'],
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
                . 'Only needed when the introduction screen has a picture.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per passage. Supply the passage in "textarea", a short "instructions" '
                . 'line, then question1/answers1/correctanswer1 (required) and up to ' . (self::MAXQUESTIONS - 1)
                . ' more numbered question/answers/correctanswer sets, each optionally with a feedbackN. Every '
                . 'filled-in question needs at least 2 answer options and a correctanswer within range. Questions should '
                . 'check the main ideas, not small details, because the learner reads fast and cannot look back.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::MEDIAQUESTION,
                    'description' => 'An optional picture for the introduction screen.',
                    'filenames' => 'Any image filename (png/jpg/gif/svg).',
                ],
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'speedreading',
                        'name' => 'Mia\'s First Night Market',
                        'instructions' => 'Read the story as fast as you can. Then answer the questions.',
                        'text' => 'Do not stop to check words you don\'t know. Read for the main ideas.',
                        'textarea' => '<p>Every Friday evening, the car park behind Mia\'s school turns into a night '
                            . 'market. ...</p>',
                        'question1' => 'Where is the night market?',
                        'answers1' => ['In a park', 'Behind Mia\'s school', 'At the train station'],
                        'correctanswer1' => 2,
                        'feedback1' => 'The first sentence says it is in the car park behind her school.',
                        'question2' => 'What does Mr. Tanaka sell?',
                        'answers2' => ['Grilled corn', 'Wooden toys', 'Pancakes'],
                        'correctanswer2' => 2,
                        'shuffleanswer' => 1,
                        'passmark' => 60,
                        'targetwpm' => 200,
                    ],
                ],
            ],
        ];
    }

    /**
     * The prompt that the AI generate method requires for speed reading.
     *
     * @param array $itemtemplate the item template
     * @param string $generatemethod one of 'extract', 'reuse' or 'generate'
     * @return string the prompt
     */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        $shape = "For each question N (N = 1 to 5) return the question text as 'questionN', ";
        $shape .= "a one dimensional array of 4 answers as 'answersN', ";
        $shape .= "the correct answer as a number 1-4 in 'correctanswerN', ";
        $shape .= "and one sentence in 'feedbackN' explaining why that answer is correct. ";
        $shape .= "The questions should check the main ideas of the passage, not small details. ";

        switch ($generatemethod) {
            case 'extract':
                $prompt = "The following {language} passage is for speed reading practice by {level} level learners: ";
                $prompt .= "[{text}] Return the passage unchanged, as HTML paragraphs, in 'textarea'. ";
                $prompt .= "Then create 5 multichoice questions in {language} about it. ";
                $prompt .= $shape;
                break;

            case 'reuse':
                // We reuse the existing data, so we do not call the AI.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Write a passage of about 250 words in {language} for speed reading practice by {level} level ";
                $prompt .= "learners, on the topic of: [{topic}] The passage must be easy for them: use only words and ";
                $prompt .= "grammar a little below their level, so they can read it quickly. Return it as HTML paragraphs ";
                $prompt .= "in 'textarea'. Then create 5 multichoice questions in {language} about it. ";
                $prompt .= $shape;
                break;
        }
        return $prompt;
    }
}
