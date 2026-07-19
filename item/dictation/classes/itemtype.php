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

namespace minilessonitem_dictation;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use stdClass;

/**
 * Renderable class for a dication item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item
{
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_LISTENING, constants::SKILL_WRITING];

    //the item type
    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output)
    {

        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);

        //ignore punctuation or require it
        $testitem->ignorepunctuation = $this->itemrecord->{constants::IGNOREPUNCTUATION} == 1;

        //sentences
        $sentences = [];
        if (isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->customtext1);
        }
        //build sentence objects containing display and phonetic text
        $testitem->phonetic = $this->itemrecord->phonetic;
        if (!empty($testitem->phonetic)) {
            $phonetics = explode(PHP_EOL, $testitem->phonetic);
        } else {
            $phonetics = [];
        }
        $is_ssml = $testitem->voiceoption == constants::TTS_SSML;
        $dottify = false;
        $testitem->sentences = $this->process_spoken_sentences($sentences, $phonetics, $dottify, $is_ssml);

        //cloudpoodll
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
    public static function validate_import($newrecord, $cm)
    {
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

        //return false to indicate no error
        return false;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'Plays a series of spoken sentences; the learner listens to each one and types what they hear, '
            . 'and the typed text is matched against the sentence. Use it for listening and writing/spelling '
            . 'practice of target sentences, phrases or vocabulary.';
    }

    /**
     * The agent-facing import field spec for dictation. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions', 'text',
            'layout']);
        $fields['type']['example'] = 'dictation';
        $fields['text']['description'] = 'Optional heading text shown above the activity, e.g. "Type what you hear".';

        $ownfields = [
            'sentences' => [
                'required' => true,
                'description' => 'The sentences to dictate, as an array of strings, one sentence per entry. '
                    . 'Each sentence is read aloud by the promptvoice and the learner types what they hear. '
                    . 'Keep sentences short enough to hold in memory while typing.',
                'example' => '["A big man jogs to his car.", "He leans on it to rest."]',
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
            'ignorepunc' => [
                'description' => 'Whether punctuation is ignored when comparing the typed text to the sentence. '
                    . 'Recommended: yes, unless punctuation is the learning point.',
                'options' => [
                    ['value' => '1', 'meaning' => 'Ignore punctuation differences (recommended)'],
                    ['value' => '0', 'meaning' => 'Require exact punctuation (default)'],
                ],
                'example' => 'yes',
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
                . 'Only needed when the item has uploaded sentence audio.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per set of sentences to dictate (around 3 to 6 sentences per item '
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
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'dictation',
                        'name' => 'Type what you hear',
                        'text' => 'Type what you hear',
                        'sentences' => [
                            'A big man jogs to his car.',
                            'He leans on it to rest.',
                            'The car falls off the cliff.',
                        ],
                        'promptvoice' => 'auto',
                        'promptvoiceopt' => 'normal',
                        'ignorepunc' => 'yes',
                    ],
                ],
            ],
        ];
    }

    /*
* This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
*/
    public static function get_keycolumns()
    {
        //get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['text5'] = ['jsonname' => 'promptvoice','type' => 'voice','optional' => true,'default' => null,'dbname' => constants::POLLYVOICE];
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt','type' => 'voiceopts','optional' => true,'default' => null,'dbname' => constants::POLLYOPTION];
        $keycols['int2'] = ['jsonname' => 'ignorepunc','type' => 'boolean','optional' => true,'default' => 0,'dbname' => constants::IGNOREPUNCTUATION];
        $keycols['text1'] = ['jsonname' => 'sentences','type' => 'stringarray','optional' => true,'default' => [],'dbname' => 'customtext1'];
        $keycols['fileanswer_audio'] = ['jsonname' => constants::FILEANSWER . '1_audio', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        return $keycols;
    }

     /*
    This function return the prompt that the generate method requires.
    */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod)
    {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Extract a 1 dimensional array of 5 sentences from the following {language} text: [{text}]. ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Generate a 1 dimensional array of 5 sentences in {language} suitable for {level} level learners on the topic of: [{topic}] ";
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
