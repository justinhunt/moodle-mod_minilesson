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

namespace minilessonitem_shortanswer;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a shortanswer item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_LISTENING, constants::SKILL_SPEAKING];

    /**
     * A short answer item stays stacked vertically however much content it carries.
     *
     * @return bool
     */
    public function autolayout_prefers_vertical() {
        return true;
    }

    public const PARTIALLYRESPONSE = 'customtext3';
    public const TOTALMARKS = 'customint1';
    public const PARTIALLYMARKS = 'customint2';
    public const RESPONSETYPE = 'customint3';

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
        $responsetype = $this->itemrecord->{self::RESPONSETYPE};
        $testitem->audiorecorder = $responsetype == constants::RESPONSE_TYPE['audiorecorder'];
        $testitem->textinput = $responsetype == constants::RESPONSE_TYPE['text'];
        $testitem->correctmarks = $this->itemrecord->{self::TOTALMARKS};
        $testitem->partialmarks = $this->itemrecord->{self::PARTIALLYMARKS};

        // sentences
        $sentences = [];
        if (isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->customtext1);
        }

        // partial answers
        $partialresponses = [];
        if (isset($testitem->{self::PARTIALLYRESPONSE})) {
            $partialresponses = explode(PHP_EOL, $testitem->{self::PARTIALLYRESPONSE});
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
        $testitem->partialresponses = $this->process_spoken_sentences($partialresponses, [], $dottify, $isssml);

        // Do we need a streaming token?
        $alternatestreaming = get_config(constants::M_COMPONENT, 'alternatestreaming');
        $isenglish = strpos($this->moduleinstance->ttslanguage, 'en') === 0;
        if ($isenglish || true) {
            $tokenobject = utils::fetch_streaming_token($this->moduleinstance->region);
            if ($tokenobject) {
                $testitem->speechtoken = $tokenobject->token;
                $testitem->speechtokenregion = '';
                $testitem->speechtokenvalidseconds = $tokenobject->validseconds;
                 $testitem->speechtokentype = $tokenobject->tokentype;
            } else {
                $testitem->speechtoken = false;
                $testitem->speechtokenvalidseconds = 0;
                $testitem->speechtokentype = '';
            }
            if ($alternatestreaming) {
                $testitem->forcestreaming = true;
            }
        }

        // Cloudpoodll.
        $testitem = $this->set_cloudpoodll_details($testitem);
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

        $answers = array_filter(array_map('trim', explode(PHP_EOL, (string) $newrecord->customtext1)), function ($answer) {
            return $answer !== '';
        });
        if (count($answers) == 0) {
            $error->col = 'customtext1';
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        // Option value checks: reject impossible values. Absent fields arrive here as their column defaults.
        $allowedresponsetypes = array_values(constants::RESPONSE_TYPE);
        if (isset($newrecord->{self::RESPONSETYPE}) && !in_array((int) $newrecord->{self::RESPONSETYPE}, $allowedresponsetypes)) {
            $error->col = self::RESPONSETYPE;
            $error->message = get_string(
                'error:invalidoptionvalue',
                constants::M_COMPONENT,
                ['value' => $newrecord->{self::RESPONSETYPE}, 'allowed' => implode(',', $allowedresponsetypes)]
            );
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
        return 'A single open question with a list of acceptable short answers. The learner answers by speaking '
            . '(graded by speech recognition) or typing, and the response is matched against the answer list. '
            . 'Use it for closed comprehension or knowledge questions whose answer is a word, phrase or short '
            . 'sentence with predictable wording. For open questions with unpredictable answers use freespeaking '
            . 'or freewriting instead.';
    }

    /**
     * The agent-facing import field spec for shortanswer. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions', 'text',
            'tts', 'ttsvoice', 'ttsoption', 'ttsautoplay', 'layout']);
        $fields['type']['example'] = 'shortanswer';
        $fields['text']['description'] = 'The question text. Add the same text to "tts" to have the question '
            . 'read aloud as well.';

        $ownfields = [
            'sentences' => [
                'required' => true,
                'description' => 'The acceptable correct answers, as an array of strings, one answer per entry. '
                    . 'The learner\'s response is matched against this list, so include the likely variants, '
                    . 'e.g. ["yellow", "It is yellow."].',
                'example' => '["yellow", "It is yellow."]',
            ],
            'partiallycorrectanswer' => [
                'description' => 'Optional partially correct answers, as an array of strings. A response matching '
                    . 'one of these earns partiallymarks instead of totalmarks.',
                'example' => '["yellowish"]',
            ],
            'alternates' => [
                'description' => 'Optional tuning for the speech recognition: acceptable alternative transcriptions '
                    . 'for specific words. An array of strings, one word set per line in the format '
                    . 'word|alternate1|alternate2, e.g. "their|there|they\'re". Only relevant when responsetype=1.',
                'example' => '["their|there|they\'re"]',
            ],
            'totalmarks' => [
                'description' => 'Marks awarded for a correct answer.',
                'example' => '2',
            ],
            'partiallymarks' => [
                'description' => 'Marks awarded for a partially correct answer. Should be less than totalmarks.',
                'example' => '1',
            ],
            'responsetype' => [
                'description' => 'How the learner answers the question.',
                'options' => [
                    ['value' => (string) constants::RESPONSE_TYPE['audiorecorder'],
                        'meaning' => 'Speak the answer into the microphone (speech recognition, default)'],
                    ['value' => (string) constants::RESPONSE_TYPE['text'],
                        'meaning' => 'Type the answer into a text box'],
                ],
            ],
        ];
        foreach ($ownfields as $jsonname => $overlay) {
            $fields[$jsonname] = static::aigen_seed_field_spec($jsonname, $overlay);
        }

        return [
            'usage' => 'Compose one item object per question. Put the question in "text" and every acceptable '
                . 'answer wording in "sentences". Choose responsetype 1 (speak) for speaking practice or 2 (type) '
                . 'for writing practice. Answers are short, so keep them to a word, phrase or short sentence.',
            'fields' => array_values($fields),
            'fileareas' => [],
            'example' => [
                'items' => [
                    [
                        'type' => 'shortanswer',
                        'name' => 'Short answer check',
                        'instructions' => 'Answer the question by using the mic.',
                        'text' => 'What color is the middle part of an egg?',
                        'tts' => 'What color is the middle part of an egg?',
                        'ttsvoice' => 'auto',
                        'sentences' => ['yellow', 'It is yellow.'],
                        'totalmarks' => 2,
                        'partiallymarks' => 1,
                        'responsetype' => constants::RESPONSE_TYPE['audiorecorder'],
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
        $keycols['text1'] = ['jsonname' => 'sentences', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => 'customtext1'];
        $keycols['text2'] = ['jsonname' => 'alternates', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => constants::ALTERNATES];
        $keycols['text3'] = ['jsonname' => 'partiallycorrectanswer', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => self::PARTIALLYRESPONSE];
        $keycols['int1'] = ['jsonname' => 'totalmarks', 'type' => 'int', 'optional' => false, 'default' => 2, 'dbname' => self::TOTALMARKS];
        $keycols['int2'] = ['jsonname' => 'partiallymarks', 'type' => 'int', 'optional' => false, 'default' => 1, 'dbname' => self::PARTIALLYMARKS];
        $keycols['int3'] = ['jsonname' => 'responsetype', 'type' => 'int', 'optional' => false, 'default' => constants::RESPONSE_TYPE['audiorecorder'], 'dbname' => self::RESPONSETYPE];
        return $keycols;
    }

    /*
    This function return the prompt that the generate method requires.
    */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Create a closed question (text) and a 1 dimensional array of  grammatically correct answers (sentences) to test the learners understanding of the following passage: [{text}] ";
                $prompt .= "The question and answers should be in {language} and suitable for {level} level learners. ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Create a closed question (text) and a 1 dimensional array of  grammatically correct answers (sentences) on the topic of: [{topic}] ";
                $prompt .= "The question and answers should be in {language} and suitable for {level} level learners. ";
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
