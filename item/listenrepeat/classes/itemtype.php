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

namespace minilessonitem_listenrepeat;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a listenrepeat item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_LISTENING, constants::SKILL_SPEAKING, constants::SKILL_PRONUNCIATION];


    // the item type
    /**
     * The class constructor.
     *
     */
    public function __construct($itemrecord, $moduleinstance = false, $context = false) {
        parent::__construct($itemrecord, $moduleinstance, $context);
        $this->needsspeechrec = true;
    }

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
        $testitem->alternates = $this->itemrecord->{constants::ALTERNATES};
        $testitem->hidestartpage = $this->itemrecord->{constants::GAPFILLHIDESTARTPAGE} == 1;

        // sentences
        $sentences = [];
        if (isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->customtext1);
        }
        // build sentence objects containing display and phonetic text
        $testitem->phonetic = $this->itemrecord->phonetic;
        if (!empty($testitem->phonetic)) {
            $phonetics = explode(PHP_EOL, $testitem->phonetic);
        } else {
            $phonetics = [];
        }
        $isssml = $testitem->voiceoption == constants::TTS_SSML;
        $dottify = false;
        $testitem->sentences = $this->process_spoken_sentences($sentences, $phonetics, $dottify, $isssml);

        // Do we need a streaming token?
        $alternatestreaming = get_config(constants::M_COMPONENT, 'alternatestreaming');
        $isenglish = strpos($this->moduleinstance->ttslanguage, 'en') === 0;
        if ($isenglish || true) {
            $tokenobject = utils::fetch_streaming_token($this->moduleinstance->region);
            if ($tokenobject) {
                $testitem->speechtoken = $tokenobject->token;
                $testitem->speechtokenregion = $tokenobject->region;
                $testitem->speechtokenvalidseconds = $tokenobject->validseconds;
                 $testitem->speechtokentype = $tokenobject->tokentype;
            } else {
                $testitem->speechtoken = false;
                $testitem->speechtokenregion = '';
                $testitem->speechtokenvalidseconds = 0;
                $testitem->speechtokentype = '';
            }
            if ($alternatestreaming) {
                $testitem->forcestreaming = true;
            }
        }

        // cloudpoodll
        $testitem = $this->set_cloudpoodll_details($testitem);
        $testitem->newui = true;
        return $testitem;
    }

    /* We need to get segmented sentences for Japanese text, ie it has to be wordified.
    * This is because we get it back from transcription wordified so we can mark up "correct" and "incorrect" words
    * We only do this for Japanese as it is the only language we support that does not use spaces to separate words
    * (sorry Korean and Chinese speakers :) )
    * We store the segmented sentence in the phonetic field, separated by || from the phonetic text. But previously
    * we fetched it at runtime so we look out for data that has not been updated to store the segmented text
    */
    protected function process_japanese_phonetics($sentence, $thephonetics = false) {
        // We have a local segmentation algorythm utils:segment_japanese but
        // sadly this segmentation algorithm mismatches with server based one we need for phonetics
        // so we are not using it. It looks like this
        // 初めまして =>(1) はじめまし て　＆　(2) はじめま　して
        // はなしてください=>(1)はな　して　く　だ　さい & (2)はな　して　ください
        if ($thephonetics) {
            $psarray = explode('|#', $thephonetics);
            $segmentedsentence = array_key_exists(1, $psarray) ? utils::super_trim($psarray[1]) : '';
            if (!empty($segmentedsentence)) {
                return $segmentedsentence;
            }
        }

        // Oh well, lets just fetch the segments now since we could not get the saved ones
        list($phones, $sentence) = utils::fetch_phones_and_segments($sentence, $this->moduleinstance->ttslanguage, $this->moduleinstance->region);
        return $sentence;
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

        $sentences = array_filter(array_map('trim', explode(PHP_EOL, (string) $newrecord->customtext1)), function ($sentence) {
            return $sentence !== '';
        });
        if (count($sentences) == 0) {
            $error->col = 'customtext1';
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        // No option value checks needed: showtextprompt is a boolean keycolumn, so the import
        // preprocessor already coerces any supplied value to 0 or 1.

        // return false to indicate no error
        return false;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'Plays a series of spoken sentences one at a time; the learner listens to each sentence and repeats '
            . '(or responds to) it aloud, and speech recognition grades what they said. Use it for pronunciation and '
            . 'speaking practice of target phrases, for drilling key sentences from a text or dialog, or for '
            . 'question-and-response practice. Requires a lesson language with speech recognition support.';
    }

    /**
     * The agent-facing import field spec for listenrepeat. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions', 'text',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'listenrepeat';
        $fields['text']['description'] = 'Optional heading text shown above the activity, e.g. "Listen and Repeat".';

        $ownfields = [
            'sentences' => [
                'required' => true,
                'description' => 'The sentences to practise, as an array of strings, one sentence per entry. '
                    . 'Each sentence is read aloud by the promptvoice and the learner repeats it. '
                    . 'For question-and-response practice an entry can use the format '
                    . '"audio prompt|correct response|text prompt" where the second and third parts are optional, '
                    . 'e.g. "How are you?|I am fine." plays the question and expects the response.',
                'example' => '["Nice to meet you.", "Where are you from?", "See you tomorrow."]',
            ],
            'promptvoice' => [
                'description' => 'The TTS voice that reads each sentence aloud. A voice display name '
                    . '(case-insensitive), e.g. "Joey" (en-US) or "Mathieu" (fr-FR), or "auto" to let the server '
                    . 'pick a voice matching the lesson language.',
                'example' => 'auto',
            ],
            'promptvoiceopt' => [
                'description' => 'Reading speed / processing option for the sentence TTS audio.',
                'options' => [
                    ['value' => 'normal', 'meaning' => 'Normal speed (default; any unrecognised value also maps to normal)'],
                    ['value' => 'slow', 'meaning' => 'Slow reading speed'],
                    ['value' => 'veryslow', 'meaning' => 'Very slow reading speed'],
                    ['value' => 'SSML', 'meaning' => 'Treat the sentences as SSML markup (this value is case-sensitive)'],
                ],
            ],
            'showtextprompt' => [
                'description' => 'Whether the text of each sentence is shown to the learner, or masked with dots '
                    . 'so they must rely on the audio.',
                'options' => [
                    ['value' => (string) constants::TEXTPROMPT_WORDS, 'meaning' => 'Show the full sentence text (default)'],
                    ['value' => (string) constants::TEXTPROMPT_DOTS, 'meaning' => 'Mask the sentence text with dots'],
                ],
            ],
            'alternates' => [
                'description' => 'Optional tuning for the speech recognition: acceptable alternative transcriptions '
                    . 'for specific words. An array of strings, one word set per line in the format '
                    . 'word|alternate1|alternate2, e.g. "their|there|they\'re".',
                'example' => '["their|there|they\'re"]',
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
                . 'Only needed when the item has uploaded sentence audio or images.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per set of sentences to practise (around 3 to 6 sentences per item '
                . 'works well). The sentences should be in the lesson language and match the learner level. '
                . 'TTS audio for each sentence is generated automatically from the promptvoice; '
                . 'only upload audio files when a specific recording is required.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::FILEANSWER . '1_audio',
                    'description' => 'Uploaded audio for the sentences (overrides the promptvoice TTS audio). '
                        . 'Usually unnecessary: prefer TTS via promptvoice.',
                    'filenames' => 'Name each file for its 1-based sentence line number: "1.mp3", "2.mp3", ...',
                ],
                [
                    'filearea' => constants::FILEANSWER . '1_image',
                    'description' => 'Optional image shown alongside each sentence.',
                    'filenames' => 'Name each file for its 1-based sentence line number: '
                        . '"1.png", "2.png", ... (.jpg is also fine).',
                ],
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'listenrepeat',
                        'name' => 'Say the sentences',
                        'instructions' => 'Listen and repeat each sentence.',
                        'text' => 'Listen and Repeat',
                        'sentences' => [
                            'Nice to meet you.',
                            'Where are you from?',
                            'See you tomorrow.',
                        ],
                        'promptvoice' => 'auto',
                        'promptvoiceopt' => 'normal',
                        'showtextprompt' => 'yes',
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
        $keycols['int1'] = ['jsonname' => 'showtextprompt', 'type' => 'boolean', 'optional' => true, 'default' => constants::TEXTPROMPT_WORDS, 'dbname' => constants::SHOWTEXTPROMPT];
        $keycols['text1'] = ['jsonname' => 'sentences', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => 'customtext1'];
        $keycols['text2'] = ['jsonname' => 'alternates', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => constants::ALTERNATES];
        $keycols['fileanswer_audio'] = ['jsonname' => constants::FILEANSWER . '1_audio', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        $keycols['fileanswer_image'] = ['jsonname' => constants::FILEANSWER . '1_image', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        return $keycols;
    }

     /*
    This function return the prompt that the generate method requires.
    */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Extract a 1 dimensional array of 4 sentences from the following {language} text: [{text}]. ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Generate a 1 dimensional array of 4 sentences in {language} suitable for {level} level learners on the topic of: [{topic}] ";
                break;
        }
        return $prompt;
    }

    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $result->hascorrectanswer = true;
        $result->correctans = $itemquizdata->sentences;
        $result->hasanswerdetails = false;
    }

}
