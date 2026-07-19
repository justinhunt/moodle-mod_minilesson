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

namespace minilessonitem_passagegapfill;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a listening gap fill item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_LISTENING, constants::SKILL_READING, constants::SKILL_WRITING];


    public const PASSAGE = 'customtext1';
    public const HINTS = 'customint5';

    // the item type
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

        // Passage Text
        $passagetext = $this->itemrecord->{self::PASSAGE};
        $plaintext = str_replace(['[', ']'], ['', ''], $passagetext);
        $passagetextwithnewlines = nl2br(s($passagetext));

        // Process the passage text to create the gaps and info that the mustache template and javascript needs.
        // we split on square brackets, so that we can identify the chunks that are to be replaced with gaps.
        $parsedchunks = [];
        $chunks = preg_split('/(\[|\])/u', $passagetextwithnewlines, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $inside = false;
        $index = -1;
        $wordindex = -1;
        foreach ($chunks as $chunk) {
            if ($chunk === '[') {
                $inside = true;
                continue;
            } else if ($chunk === ']') {
                $inside = false;
                continue;
            } else if ($inside) {
                // This is the bracketed word – create placeholder
                $placeholder = \core_text::substr($chunk, 0, 1) . str_repeat('&#x2022;', mb_strlen($chunk) - 1);
                $text = $chunk;
                $isgap = true;
                $index++;
                $wordindex++;
            } else {
                // Regular text (including punctuation, partial words, etc.)
                $placeholder = '';
                $text = $chunk;
                $isgap = false;
                $index++;
            }

            switch ($this->language) {
                case 'ar-SA':
                case 'ar-AE':
                case 'fa-IR':
                case 'he-IL':
                case 'ps-AF':
                    $textpadding = 2; // RTL langs seem to be wider and need more padding for proper display.
                    break;
                default:
                    $textpadding = 1;
            }
            $parsedchunks[$index] = [
                'wordindex' => $wordindex,
                'text' => $text,
                'placeholder' => $placeholder,
                'isgap' => $isgap,
                'textlength' => mb_strlen($text),
                'paddedtextlength' => mb_strlen($text) + $textpadding,
            ];
        }
        $passagedata = ['rawtext' => $passagetext, 'plaintext' => $plaintext, 'chunks' => $parsedchunks];
        $testitem->passagedata = $passagedata;

        // Item audio
        if ($this->itemrecord->{constants::POLLYOPTION} != constants::TTS_NOTTS) {
            $testitem->passageaudio = utils::fetch_polly_url(
                $this->token,
                $this->region,
                $plaintext,
                $this->itemrecord->{constants::POLLYOPTION},
                $this->itemrecord->{constants::POLLYVOICE},
                $this->moduleinstance->id
            );
        } else {
            $custompassageaudio = $this->fetch_sentence_media('audio', 1);
            if ($custompassageaudio && count($custompassageaudio) > 0) {
                $testitem->passageaudio = array_shift($custompassageaudio);
            } else {
                $testitem->passageaudio = false;
            }
        }

        // Cloudpoodll
        $testitem = $this->set_cloudpoodll_details($testitem);
        // Hints gone from function so regain it here
        $testitem->hints = $this->itemrecord->{self::HINTS} == 0 ? false : $this->itemrecord->{self::HINTS};
        $testitem->althintstring = get_string('anotherhint', constants::M_COMPONENT);
        $testitem->penalizehints = $this->itemrecord->{constants::PENALIZEHINTS} == 1;
        return $testitem;
    }

    /**
     * Validates an import record for this item type.
     *
     * @param \stdClass $newrecord the db-ready import record
     * @param \stdClass $cm the course module
     * @return false|\stdClass false when valid, or an error object with col and message
     */
    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        $passage = trim((string) $newrecord->{self::PASSAGE});
        if ($passage == '') {
            $error->col = self::PASSAGE;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }
        if (!preg_match('/\[[^\]]+\]/', $passage)) {
            $error->col = self::PASSAGE;
            $error->message = get_string('error:nogaps', constants::M_COMPONENT);
            return $error;
        }

        // return false to indicate no error
        return false;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'A passage of text with words gapped out; the learner listens to the passage read aloud by TTS '
            . 'and types the missing words into the gaps. Use it for extended listening + writing practice with '
            . 'a story or paragraph, e.g. to focus on key vocabulary in context. For independent single-sentence '
            . 'gaps use typinggapfill, listeninggapfill or speakinggapfill instead.';
    }

    /**
     * The agent-facing import field spec for passagegapfill. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'passagegapfill';

        $ownfields = [
            'passage' => [
                'description' => 'The passage text, with each gapped word enclosed in square brackets, '
                    . 'e.g. "This is my [dog] and my [cat]." The learner hears the full passage and types '
                    . 'the bracketed words. Gap whole words, and keep the number of gaps reasonable '
                    . '(roughly one per sentence).',
                'example' => 'The [story] is about a girl. She leaves the ball at [midnight] and loses her [glass] slipper.',
            ],
            'promptvoice' => [
                'description' => 'The TTS voice that reads the passage aloud. A voice display name '
                    . '(case-insensitive), e.g. "Joey" (en-US) or "Mathieu" (fr-FR), or "auto" to let the server '
                    . 'pick a voice matching the lesson language.',
                'example' => 'auto',
            ],
            'promptvoiceopt' => [
                'description' => 'Reading speed / processing option for the passage TTS audio. '
                    . '"slow" is often a good choice for gapfill listening.',
                'options' => [
                    ['value' => 'normal', 'meaning' => 'Normal speed (default; any unrecognised value also maps to normal)'],
                    ['value' => 'slow', 'meaning' => 'Slow reading speed'],
                    ['value' => 'veryslow', 'meaning' => 'Very slow reading speed'],
                ],
            ],
            'hidestartpage' => [
                'description' => 'Whether the activity begins as soon as it has loaded, instead of showing a '
                    . 'start/splash page first.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Show the start page (default)'],
                    ['value' => '1', 'meaning' => 'Start immediately'],
                ],
            ],
            'penalizehints' => [
                'description' => 'Whether using hints reduces the score.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Hints are free (default)'],
                    ['value' => '1', 'meaning' => 'Using a hint costs marks'],
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
                . 'Only needed when the item has uploaded passage audio.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per passage. Write the passage in the lesson language with the '
                . 'gap words in square brackets. TTS audio for the passage is generated automatically from the '
                . 'promptvoice; only upload audio when a specific recording is required.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::FILEANSWER . '1_audio',
                    'description' => 'Uploaded audio recording of the passage (overrides the promptvoice TTS audio). '
                        . 'Usually unnecessary: prefer TTS via promptvoice.',
                    'filenames' => 'A single audio file, e.g. "1.mp3".',
                ],
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'passagegapfill',
                        'name' => 'Passage gapfill',
                        'instructions' => 'Fill in each of the missing words.',
                        'passage' => '"Cinderella" is a [fairy] tale. The [story] is about a girl who was treated '
                            . 'badly. She leaves the ball at [midnight] and loses her [glass] slipper.',
                        'promptvoice' => 'auto',
                        'promptvoiceopt' => 'slow',
                        'hidestartpage' => 'yes',
                    ],
                ],
            ],
        ];
    }

    /*
    * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
    */
    public static function get_keycolumns() {
        // get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt', 'type' => 'voiceopts', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYOPTION];
        $keycols['text5'] = ['jsonname' => 'promptvoice', 'type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYVOICE];
        $keycols['text1'] = ['jsonname' => 'passage', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => self::PASSAGE];
        $keycols['int5'] = ['jsonname' => 'hidestartpage', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => self::HINTS];
        $keycols['int2'] = ['jsonname' => 'penalizehints', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::PENALIZEHINTS];
        $keycols['fileanswer_audio'] = ['jsonname' => constants::FILEANSWER . '1_audio', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        return $keycols;
    }

    /*
    This function return the prompt that the generate method requires.
    */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Choose 8 keywords from the following {language} text. ";
                $prompt .= "Surround each instance of the keyword in the passage with square brackets, e.g [word].  " . PHP_EOL;
                $prompt .= " [{text}]. ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Generate a passage of text in {language} suitable for {level} level learners on the topic of: [{topic}] " . PHP_EOL;
                $prompt .= "The passage should be about 6 sentences long. " . PHP_EOL;
                $prompt .= "The passage of text should contain the following keywords: [{keywords}] " . PHP_EOL;
                $prompt .= "Each instance of a keyword in the passage should be surrounded with square brackets, e.g [word].  " . PHP_EOL;
                $prompt .= "The passage should be engaging and appropriate for the target audience." . PHP_EOL;
                break;
        }
        return $prompt;
    }

    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $resultsdata = isset($result->resultsdata) ? $result->resultsdata : null;
        $hasitems = $resultsdata && isset($resultsdata->items) && $resultsdata->items && $resultsdata->items > 0;
        if (!$hasitems || !$resultsdata) {
            $result->correctans = [];
            $result->incorrectans = [];
            return;
        }
        $result->hascorrectanswer = $hasitems;
        $result->hasincorrectanswer = $hasitems;
        $result->hasanswerdetails = false;
        $resultsdata = isset($result->resultsdata) ? $result->resultsdata : null;
        $correctanswers = [];
        $incorrectanswers = [];

        $items = $resultsdata->items;
        for ($i = 0; $i < count($items); $i++) {
            $theitem = $resultsdata->items[$i];
            if (!$theitem) {
                continue;
            }
            if ($theitem->correct) {
                $correctanswers[] = ['sentence' => $theitem->text];
            } else {
                $incorrectanswers[] = ['sentence' => $theitem->text];
            }
        }
        $result->correctans = $correctanswers;
        $result->incorrectans = $incorrectanswers;
    }
}
