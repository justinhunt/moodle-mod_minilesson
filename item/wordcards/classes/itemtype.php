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

namespace minilessonitem_wordcards;

use mod_minilesson\local\itemtype\item;
use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a wordcards item in a minilesson activity.
 *
 * @package    minilessonitem_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_VOCABULARY, constants::SKILL_LISTENING, constants::SKILL_WRITING];

    /**
     * This item type has a splash screen, drawn as a centred white card.
     *
     * @return bool
     */
    public function uses_boxed_layout() {
        return true;
    }

    /** The practice type (one of the MODE_ constants below). */
    public const ACTIVITYTYPE = 'customint1';

    /** Hear the term, type it on the onscreen keyboard. */
    public const MODE_LISTENTYPE = 0;
    /** Hear the term, choose it from a set of terms. */
    public const MODE_LISTENCHOOSE = 1;
    /** See the definition, type the term on the onscreen keyboard. */
    public const MODE_TYPEWORD = 2;
    /** See the definition, choose the term from a set of terms. */
    public const MODE_CHOOSEWORD = 3;

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);

        // Words.
        $lines = [];
        if (isset($testitem->customtext1)) {
            $lines = explode(PHP_EOL, $testitem->customtext1);
        }
        $testitem->words = $this->process_word_lines($lines);

        // Shuffle so the word order is not predictable (as scatter does).
        // The media urls are already bound to each word object, so they travel with it.
        // We only renumber index/indexplusone so the DOM order stays in sync with the JS pointer.
        shuffle($testitem->words);
        foreach ($testitem->words as $newindex => $word) {
            $word->index = $newindex;
            $word->indexplusone = $newindex + 1;
        }

        // Practice type flags for the template and JS.
        $activitytype = (int)($this->itemrecord->{self::ACTIVITYTYPE} ?? self::MODE_LISTENTYPE);
        $testitem->activitytype = $activitytype;
        $testitem->islistenmode = in_array($activitytype, [self::MODE_LISTENTYPE, self::MODE_LISTENCHOOSE]);
        $testitem->isdefmode = in_array($activitytype, [self::MODE_TYPEWORD, self::MODE_CHOOSEWORD]);
        $testitem->istypemode = in_array($activitytype, [self::MODE_LISTENTYPE, self::MODE_TYPEWORD]);
        $testitem->ischoosemode = in_array($activitytype, [self::MODE_LISTENCHOOSE, self::MODE_CHOOSEWORD]);

        $testitem->hidestartpage = $this->itemrecord->{constants::GAPFILLHIDESTARTPAGE} == 1;

        return $testitem;
    }

    /**
     * Turns the raw "term|definition" lines into word objects for the template/JS.
     *
     * Uploaded media is matched to words by line number: files named 1.mp3 / 2.png etc.
     * bind to the word on that (1-based) line. Words with no uploaded audio fall back
     * to a Polly TTS URL using the item's voice settings.
     *
     * @param array $lines raw lines from customtext1
     * @return array of word objects {index, indexplusone, term, definition, audiourl, imageurl}
     */
    protected function process_word_lines($lines) {
        $customaudio = $this->fetch_sentence_media('audio', 1);
        $customimages = $this->fetch_sentence_media('image', 1);

        $words = [];
        $index = 0;
        foreach ($lines as $line) {
            $line = utils::super_trim($line);
            if (empty($line)) {
                continue;
            }
            $parts = explode('|', $line, 2);
            $term = utils::super_trim($parts[0]);
            $definition = count($parts) > 1 ? utils::super_trim($parts[1]) : '';
            if (empty($term)) {
                continue;
            }

            $word = new stdClass();
            $word->index = $index;
            $word->indexplusone = $index + 1;
            $word->term = $term;
            $word->definition = $definition;

            // Uploaded audio wins, otherwise we use Polly TTS.
            if (isset($customaudio[$word->indexplusone])) {
                $word->audiourl = $customaudio[$word->indexplusone];
            } else {
                $word->audiourl = utils::fetch_polly_url(
                    $this->token,
                    $this->region,
                    $term,
                    $this->itemrecord->{constants::POLLYOPTION},
                    $this->itemrecord->{constants::POLLYVOICE},
                    $this->moduleinstance->id
                );
            }
            $word->imageurl = $customimages[$word->indexplusone] ?? false;

            $index++;
            $words[] = $word;
        }
        return $words;
    }

    /**
     * Validates an imported item record before it is saved.
     *
     * @param stdClass $newrecord the record built from the import data
     * @param stdClass $cm the course module
     * @return stdClass|false an error object {col, message}, or false if there is no error
     */
    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if ($newrecord->customtext1 == '') {
            $error->col = 'customtext1';
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        $lines = array_filter(array_map([utils::class, 'super_trim'], explode(PHP_EOL, $newrecord->customtext1)));
        if (count($lines) < 2) {
            $error->col = 'customtext1';
            $error->message = get_string('error:atleasttwowords', 'minilessonitem_wordcards');
            return $error;
        }
        foreach (array_values($lines) as $i => $line) {
            $parts = explode('|', $line, 2);
            if (utils::super_trim($parts[0]) === '') {
                $error->col = 'customtext1';
                $error->message = get_string('error:badwordline', 'minilessonitem_wordcards', $i + 1);
                return $error;
            }
        }

        // Option value check: reject impossible activity types.
        $allowedtypes = [self::MODE_LISTENTYPE, self::MODE_LISTENCHOOSE, self::MODE_TYPEWORD, self::MODE_CHOOSEWORD];
        if (isset($newrecord->{self::ACTIVITYTYPE}) && !in_array((int) $newrecord->{self::ACTIVITYTYPE}, $allowedtypes)) {
            $error->col = self::ACTIVITYTYPE;
            $error->message = get_string(
                'error:invalidoptionvalue',
                constants::M_COMPONENT,
                ['value' => $newrecord->{self::ACTIVITYTYPE}, 'allowed' => implode(',', $allowedtypes)]
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
        return 'A vocabulary practice item: the learner first reviews a grid of flip cards (word on the front, '
            . 'definition on the back), then works through one question per word in the chosen practice mode - '
            . 'typing or choosing each word from its audio or definition. Use it to practise a set of target '
            . 'vocabulary; several wordcards items with the same words but different activitytype values make '
            . 'a good progression (e.g. choose the word, then type the word, then listen and type).';
    }

    /**
     * The agent-facing import field spec for wordcards. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions', 'text',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'wordcards';
        $fields['text']['description'] = 'Optional heading text shown above the activity, e.g. "Choose the word".';

        $ownfields = [
            'words' => [
                'required' => true,
                'description' => 'The words to practise, as an array of strings, one word per entry in the format '
                    . '"word|definition". The definition can be a translation or a simple definition; words cannot '
                    . 'contain the "|" character. At least 2 words are required; 4 to 8 works well for the '
                    . 'choice-based practice types (choice questions offer the correct word plus up to 4 '
                    . 'distractors drawn from the other words).',
                'example' => '["family|the people you are related to", "mother|a female parent"]',
            ],
            'activitytype' => [
                'description' => 'The practice mode used for the questions (one mode per item; use several '
                    . 'wordcards items for several modes).',
                'options' => [
                    ['value' => (string) self::MODE_LISTENTYPE,
                        'meaning' => 'Listen and type: the learner hears the word and types it (default)'],
                    ['value' => (string) self::MODE_LISTENCHOOSE,
                        'meaning' => 'Listen and choose: the learner hears the word and chooses it from a list of words'],
                    ['value' => (string) self::MODE_TYPEWORD,
                        'meaning' => 'Type the word: the learner sees the definition and types the word'],
                    ['value' => (string) self::MODE_CHOOSEWORD,
                        'meaning' => 'Choose the word: the learner sees the definition and chooses the word from a list'],
                ],
            ],
            'promptvoice' => [
                'description' => 'The TTS voice that speaks each word (used by the listening practice types and '
                    . 'the flip cards). A voice display name (case-insensitive), e.g. "Joey" (en-US) or "Mathieu" '
                    . '(fr-FR), or "auto" to let the server pick a voice matching the lesson language.',
                'example' => 'auto',
            ],
            'promptvoiceopt' => [
                'description' => 'Reading speed / processing option for the word TTS audio.',
                'options' => [
                    ['value' => 'normal', 'meaning' => 'Normal speed (default; any unrecognised value also maps to normal)'],
                    ['value' => 'slow', 'meaning' => 'Slow reading speed'],
                    ['value' => 'veryslow', 'meaning' => 'Very slow reading speed'],
                ],
            ],
            'hidestartpage' => [
                'description' => 'Whether the opening flip-card review grid is skipped, going straight to the '
                    . 'practice questions.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Show the flip-card review grid first (default)'],
                    ['value' => '1', 'meaning' => 'Skip straight to the questions'],
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
                . 'Only needed when the words have uploaded audio or images.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per practice set. Use the same word list across several items '
                . 'with different activitytype values to build a practice progression from recognition to '
                . 'production. TTS audio for the words is generated automatically from the promptvoice. '
                . 'Optional images (shown as a hint during the questions) go in the '
                . constants::FILEANSWER . '1_image file area, matched to words by line number.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::FILEANSWER . '1_audio',
                    'description' => 'Uploaded audio for the words (overrides the promptvoice TTS audio). '
                        . 'Usually unnecessary: prefer TTS via promptvoice.',
                    'filenames' => 'Name each file for its 1-based word line number: "1.mp3", "2.mp3", ...',
                ],
                [
                    'filearea' => constants::FILEANSWER . '1_image',
                    'description' => 'Optional image for each word, shown as a visual hint during the questions.',
                    'filenames' => 'Name each file for its 1-based word line number: '
                        . '"1.png", "2.png", ... (.jpg is also fine).',
                ],
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'wordcards',
                        'name' => 'Word cards: family words',
                        'instructions' => 'Complete the task for each word.',
                        'text' => 'Choose the word',
                        'words' => [
                            'family|the people you are related to',
                            'mother|a female parent',
                            'father|a male parent',
                            'brother|a male sibling',
                            'sister|a female sibling',
                        ],
                        'activitytype' => self::MODE_CHOOSEWORD,
                        'promptvoice' => 'auto',
                        'promptvoiceopt' => 'normal',
                    ],
                ],
            ],
        ];
    }

    /**
     * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
     *
     * @return array the key column definitions
     */
    public static function get_keycolumns() {
        // Get the basic key columns and customize a little for instances of this item type.
        $keycols = parent::get_keycolumns();
        $keycols['int1'] = ['jsonname' => 'activitytype', 'type' => 'int', 'optional' => true,
            'default' => 0, 'dbname' => self::ACTIVITYTYPE];
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt', 'type' => 'voiceopts', 'optional' => true,
            'default' => null, 'dbname' => constants::POLLYOPTION];
        $keycols['text5'] = ['jsonname' => 'promptvoice', 'type' => 'voice', 'optional' => true,
            'default' => null, 'dbname' => constants::POLLYVOICE];
        $keycols['int5'] = ['jsonname' => 'hidestartpage', 'type' => 'boolean', 'optional' => true,
            'default' => 0, 'dbname' => constants::GAPFILLHIDESTARTPAGE];
        $keycols['text1'] = ['jsonname' => 'words', 'type' => 'stringarray', 'optional' => true,
            'default' => [], 'dbname' => 'customtext1'];
        $keycols['fileanswer_audio'] = ['jsonname' => constants::FILEANSWER . '1_audio', 'type' => 'anonymousfile',
            'optional' => true, 'default' => null, 'dbname' => false];
        $keycols['fileanswer_image'] = ['jsonname' => constants::FILEANSWER . '1_image', 'type' => 'anonymousfile',
            'optional' => true, 'default' => null, 'dbname' => false];
        return $keycols;
    }

    /**
     * Shapes the correct answers shown on the attempt review page.
     *
     * @param stdClass $result the result object to decorate
     * @param stdClass $itemquizdata the exported item data
     * @return void
     */
    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $result->hascorrectanswer = true;
        $result->correctans = array_map(function ($word) {
            $sentence = new stdClass();
            $sentence->sentence = $word->term . ($word->definition !== '' ? ' — ' . $word->definition : '');
            return $sentence;
        }, $itemquizdata->words);
        $result->hasanswerdetails = false;
    }
}
