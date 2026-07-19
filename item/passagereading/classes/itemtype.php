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

namespace minilessonitem_passagereading;

use mod_minilesson\aitranscriptutils;
use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a page item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_READING, constants::SKILL_SPEAKING, constants::SKILL_PRONUNCIATION];


    public const PASSAGE = 'customtext1';

    // The item type.
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
        $testitem = $this->set_layout($testitem);
        $testitem->alternates = $this->itemrecord->{constants::ALTERNATES};
        $testitem->passagetext = $this->itemrecord->{self::PASSAGE};
        $testitem->passagehtml = \mod_minilesson\aitranscriptutils::render_passage($this->itemrecord->{self::PASSAGE});

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
        $testitem->savemedia = 1; // For now this is disabled
        $testitem->savemediaregion = $this->moduleinstance->region;
        $testitem->transcode = 1;
        $testitem->expiredays = 365;

        // Cloudpoodll.
        $maxtime = $this->itemrecord->timelimit;
        $testitem = $this->set_cloudpoodll_details($testitem, $maxtime);

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

        if (trim((string) $newrecord->{self::PASSAGE}) == '') {
            $error->col = self::PASSAGE;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
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
        return 'A passage of text the learner reads aloud into the microphone; speech recognition marks each '
            . 'word as read correctly or not, and the score is the percentage read correctly. Use it for reading '
            . 'fluency and pronunciation practice with a story or paragraph. For practising isolated sentences '
            . 'use listenrepeat or speechcards instead. Requires a lesson language with speech recognition support.';
    }

    /**
     * The agent-facing import field spec for passagereading. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'passagereading';

        $ownfields = [
            'passage' => [
                'description' => 'The passage the learner reads aloud, as plain text. Blank lines separate '
                    . 'paragraphs. Keep the length appropriate to the learner level - about a minute of reading '
                    . 'works well. Avoid unusual symbols or formatting that would not be spoken.',
                'example' => 'Mister Tanaka is the owner of a small public relations company in Japan. '
                    . 'He is heading to a publishers conference in New York City.',
            ],
            'alternates' => [
                'description' => 'Optional tuning for the speech recognition: acceptable alternative transcriptions '
                    . 'for specific passage words. An array of strings, one word set per line in the format '
                    . 'word|alternate1|alternate2, e.g. "their|there|they\'re".',
                'example' => '["their|there|they\'re"]',
            ],
            'totalmarks' => [
                'description' => 'The marks this item contributes. 0 (default) = the total marks equal the passage '
                    . 'word count; any other value scales the score (the percentage of the passage read correctly) '
                    . 'out of that value.',
                'example' => '5',
            ],
        ];
        foreach ($ownfields as $jsonname => $overlay) {
            $fields[$jsonname] = static::aigen_seed_field_spec($jsonname, $overlay);
        }

        return [
            'usage' => 'Compose one item object per passage. Write the passage in the lesson language at the '
                . 'learner\'s level. The learner must read the whole passage aloud, so keep it short for lower '
                . 'levels. A timelimit can be set for fluency-focused reading.',
            'fields' => array_values($fields),
            'fileareas' => [],
            'example' => [
                'items' => [
                    [
                        'type' => 'passagereading',
                        'name' => 'Read the passage',
                        'instructions' => 'Use the microphone to record yourself reading the passage.',
                        'passage' => 'Mister Tanaka is the owner of a small public relations company in Japan. '
                            . 'He is heading to a publishers conference in New York City to represent his client. '
                            . "\n\n"
                            . 'He arrives at the airport at about 8 o\'clock in the morning, three hours before '
                            . 'his flight. He checks his memo to make sure that he has everything he needs.',
                        'totalmarks' => 5,
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
        $keycols['int1'] = ['jsonname' => 'totalmarks', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::TOTALMARKS];
        $keycols['text1'] = ['jsonname' => 'passage', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => self::PASSAGE];
        $keycols['text2'] = ['jsonname' => 'alternates', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => constants::ALTERNATES];
        return $keycols;
    }

    /*
    This function return the prompt that the generate method requires.
    */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Create a {language} passage that is a 5 or 6 sentence summarisation of the following text: [{text}]. ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Generate a passage of text in {language} suitable for {level} level learners on the topic of: [{topic}] " . PHP_EOL;
                $prompt .= "The passage should be about 6 sentences long. ";
                break;
        }
        return $prompt;
    }

    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $items = $this->itemrecord;
        $result->hascorrectanswer = false;
        $result->hasincorrectanswer = false;
        if (
            isset($result->resultsdata)
            && isset($result->resultsdata->read)
            && ($result->resultsdata->read + $result->resultsdata->unreached) > 0
        ) {
            $result->hasanswerdetails = true;
            $result->resultstemplate = self::get_component() . '/passagereadingreviewresults';
            $result->resultsdata->passagehtml = aitranscriptutils::render_passage(
                $items->{self::PASSAGE}
            );
            $result->resultsdatajson = json_encode(
                $result->resultsdata,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } else {
            $result->hasanswerdetails = false;
        }
    }
}
