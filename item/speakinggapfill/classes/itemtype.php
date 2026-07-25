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

namespace minilessonitem_speakinggapfill;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a speakinggapfill item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_SPEAKING, constants::SKILL_GRAMMAR];

    /**
     * The item text is one gapfill sentence per line, carrying gap markup, so strip the markup
     * before handing it to the speech test.
     *
     * @param \stdClass $itemrecord The item's DB record.
     * @param string $default The text the caller derived from the item record.
     * @return string
     */
    public function get_speechtester_text($itemrecord, $default) {
        $text = "";
        if (isset($itemrecord->customtext1)) {
            $sentences = explode(PHP_EOL, $itemrecord->customtext1);
            foreach ($this->parse_gapfill_sentences($sentences) as $sentencedata) {
                $text .= $sentencedata->sentence . '<br/>';
            }
        }
        return $text;
    }

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

        // Sentences
        $sentences = [];
        if (isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->customtext1);
        }

        $testitem->sentences = $this->process_speakinggapfill_sentences($sentences);

        // If shuffle order is on, randomize the order the sentences are delivered in.
        // The audio, phonetics and gap data are already bound to each sentence object, so they travel with it.
        // We only renumber index/indexplusone so the DOM order stays in sync with the JS pointer.
        if (!empty($this->itemrecord->{constants::GAPFILLSHUFFLEORDER})) {
            shuffle($testitem->sentences);
            foreach ($testitem->sentences as $newindex => $thesentence) {
                $thesentence->index = $newindex;
                $thesentence->indexplusone = $newindex + 1;
            }
        }

        $testitem->hintrtl = $this->itemrecord->{constants::GAPFILLHINTRTL} == 1;
        $testitem->readsentence = $this->itemrecord->{constants::READSENTENCE} == 1;
        $testitem->allowretry = $this->itemrecord->{constants::GAPFILLALLOWRETRY} == 1;
        $testitem->hidestartpage = $this->itemrecord->{constants::GAPFILLHIDESTARTPAGE} == 1;

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

        // add a few things to enable the saving of uploaded audio (on S3)
        $testitem->savemedia = 0; // For now this is disabled
        $testitem->savemediaregion = $this->moduleinstance->region;
        $testitem->transcode = 1;
        $testitem->expiredays = 365;

        // Cloudpoodll
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
        if (!preg_match('/\[[^\]]+\]/', (string) $newrecord->customtext1)) {
            $error->col = 'customtext1';
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
        return 'A set of sentences each with letters gapped out; the learner works out the missing letters '
            . '(from the sentence, an optional hint, or - in dictation style - the sentence read aloud) and then '
            . 'speaks the complete sentence into the microphone, graded by speech recognition. Use it for '
            . 'speaking practice with a focus on target vocabulary or grammar in context. '
            . 'Requires a lesson language with speech recognition support.';
    }

    /**
     * The agent-facing import field spec for speakinggapfill. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'speakinggapfill';

        $fields += static::aigen_gapfill_shared_field_specs();

        $ownfields = [
            'promptvoice' => [
                'description' => 'The TTS voice that reads the sentences aloud (used in dictation style). '
                    . 'A voice display name (case-insensitive), e.g. "Joey" (en-US) or "Mathieu" (fr-FR), '
                    . 'or "auto" to let the server pick a voice matching the lesson language.',
                'example' => 'auto',
            ],
            'promptvoiceopt' => [
                'description' => 'Reading speed / processing option for the sentence TTS audio.',
                'options' => [
                    ['value' => 'normal', 'meaning' => 'Normal speed (default; any unrecognised value also maps to normal)'],
                    ['value' => 'slow', 'meaning' => 'Slow reading speed'],
                    ['value' => 'veryslow', 'meaning' => 'Very slow reading speed'],
                ],
            ],
            'dictationstyle' => [
                'description' => 'Whether each sentence is read aloud by TTS before the learner speaks it - '
                    . 'a form of dictation.',
                'options' => [
                    ['value' => '0', 'meaning' => 'No audio: the learner works from the written sentence and hint (default)'],
                    ['value' => '1', 'meaning' => 'Each sentence is read aloud first'],
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
                . 'Only needed when the sentences have uploaded audio or images.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per set of sentences (around 5 to 8 works well). '
                . 'In each sentence enclose the gap letters in square brackets and add a hint after a pipe '
                . 'where helpful - hints in the learner\'s native language work well. The learner must say the '
                . 'whole sentence aloud, so keep sentences short enough to speak comfortably.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::FILEANSWER . '1_audio',
                    'description' => 'Uploaded audio for the sentences (overrides the promptvoice TTS audio '
                        . 'in dictation style). Usually unnecessary: prefer TTS via promptvoice.',
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
                        'type' => 'speakinggapfill',
                        'name' => 'Speaking gapfill',
                        'instructions' => 'Listen to the audio, read the hint and then using the microphone '
                            . 'read the complete sentence aloud.',
                        'sentences' => [
                            'Could you [spell] your name for me, please?|write out the letters',
                            'Please pr[epare] the vegetables for lunch.|get ready',
                            'Do you have time to di[scuss] it now?|talk about',
                        ],
                        'promptvoice' => 'auto',
                        'promptvoiceopt' => 'normal',
                        'dictationstyle' => 'yes',
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
        $keycols['int3'] = ['jsonname' => 'allowretry', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::GAPFILLALLOWRETRY];
        $keycols['int1'] = ['jsonname' => 'shuffleorder', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::GAPFILLSHUFFLEORDER];
        $keycols['int2'] = ['jsonname' => 'dictationstyle', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::READSENTENCE];
        $keycols['text1'] = ['jsonname' => 'sentences', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => 'customtext1'];
        $keycols['int5'] = ['jsonname' => 'hidestartpage', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::GAPFILLHIDESTARTPAGE];
        $keycols['text2'] = ['jsonname' => 'alternates', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => constants::ALTERNATES];
        $keycols['int6'] = ['jsonname' => 'hintrtl', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::GAPFILLHINTRTL];
        $keycols['fileanswer_audio'] = ['jsonname' => constants::FILEANSWER . '1_audio', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        $keycols['fileanswer_image'] = ['jsonname' => constants::FILEANSWER . '1_image', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        return $keycols;
    }

    public function update_create_langmodel($olditemrecord) {
        // If we need to generate a DeepSpeech model for this, then lets do that now.
        // We want to process the hashcode and lang model if it makes sense.

        $passage = '';

        // Sentences
        $sentences = [];
        if (isset($this->itemrecord->customtext1)) {
            $sentences = explode(PHP_EOL, $this->itemrecord->customtext1);
        }
        $sentencedata = $this->parse_gapfill_sentences($sentences);
        foreach ($sentencedata as $sentence) {
            $passage .= $sentence->prompt . ' ';
        }

        if (utils::needs_lang_model($this->moduleinstance, $passage)) {
            $newpassagehash = utils::fetch_passagehash($this->language, $passage);
            if ($newpassagehash) {
                // check if it has changed, if its a brand new one, if so register a langmodel
                if (!$olditemrecord || $olditemrecord->passagehash != ($this->region . '|' . $newpassagehash)) {
                    // build a lang model
                    $ret = utils::fetch_lang_model($passage, $this->language, $this->region);

                    // for doing a dry run
                    // $ret=new \stdClass();
                    // $ret->success=true;

                    if ($ret && isset($ret->success) && $ret->success) {
                        $this->itemrecord->passagehash = $this->region . '|' . $newpassagehash;
                        return true;
                    }
                }
            }
            // if we get here just set the new passage hash to the existing one
            if ($olditemrecord) {
                $this->itemrecord->passagehash = $olditemrecord->passagehash;
            } else {
                // This would happen if the user changed region, forcing an update, but there was no valid cloud poodll token
                $this->itemrecord->passagehash = '';
            }
        } else {
            // I think this will never get here
            $this->itemrecord->passagehash = '';
        }
        return false;
    }

    /*
    * This function return the prompt that the generate method requires for listening gap fill items.
    */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Extract a 1 dimensional array of 4 sentences from the following {language} text: [{text}]. ";
                $prompt .= "In each sentence surround one keyword with square brackets, e.g [word]. ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Generate a 1 dimensional array of 4 sentences in {language} suitable for {level} level learners on the topic of: [{topic}] ";
                $prompt .= "In each sentence surround one keyword with square brackets, e.g [word]. ";
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
