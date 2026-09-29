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

namespace minilessonitem_selfcheck;

use mod_minilesson\local\itemtype\item;
use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a self check item in a minilesson activity.
 *
 * The student records each sentence, hears the model audio, their own recording and the model audio again,
 * and then approves their attempt or tries again. There is no speech recognition, so it works in any language.
 *
 * @package    minilessonitem_selfcheck
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_SPEAKING, constants::SKILL_PRONUNCIATION];

    /** @var int The longest a single recording may run, in seconds. */
    public const MAXRECORDINGTIME = 30;

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

        // Is rtl.
        $testitem->rtl = utils::is_rtl($this->language);
        $testitem->hintrtl = $this->itemrecord->{constants::FLUENCYHINTRTL} == 1;
        $testitem->hidestartpage = $this->itemrecord->{constants::GAPFILLHIDESTARTPAGE} == 1;

        // Cloud Poodll. We use it for the recorder and for saving the recording, not for speech recognition.
        $testitem = $this->set_cloudpoodll_details($testitem, self::MAXRECORDINGTIME);
        $testitem->speechtoken = false;
        $testitem->speechtokenvalidseconds = 0;
        $testitem->speechtokentype = '';

        // Save the recordings (on S3) so they can be reviewed later. We can only do that with a Cloud Poodll token.
        $testitem->savemedia = empty($this->token) ? 0 : 1;
        $testitem->transcode = 1;
        $testitem->expiredays = 365;
        $testitem->savemediaregion = $this->moduleinstance->region;

        // Build sentence objects.
        $sentences = [];
        if (isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->customtext1);
        }
        $testitem->sentences = $this->process_spoken_sentences($sentences, []);
        // Our template shows the instructions and item text itself, so the question header should not.
        $testitem->newui = true;
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

        $sentences = array_filter(array_map('trim', explode(PHP_EOL, (string) $newrecord->customtext1)), function ($sentence) {
            return $sentence !== '';
        });
        if (count($sentences) == 0) {
            $error->col = 'customtext1';
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
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
        return 'A series of sentences the learner says aloud and then checks for themselves: after recording, '
            . 'they hear the model audio, their own recording and the model audio again, and then approve their '
            . 'attempt or try again. There is no speech recognition, so use it for languages that speech '
            . 'recognition does not support (e.g. Maori or Basque), or when the teacher does not want automatic '
            . 'speech scoring. Where speech recognition is available and wanted, prefer fluency or listenrepeat.';
    }

    /**
     * The agent-facing import field spec for selfcheck. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'selfcheck';

        $ownfields = [
            'sentences' => [
                'required' => true,
                'description' => 'The sentences to speak, as an array of strings, one per entry. A hint or '
                    . 'translation (shown to the learner) can follow the sentence after a pipe, '
                    . 'e.g. "Kia ora|Hello". Around 3 to 6 sentences per item works well.',
                'example' => '["Kia ora.|Hello.", "Kei te pēhea koe?|How are you?"]',
            ],
            'promptvoice' => [
                'description' => 'The TTS voice that provides model audio of each sentence. A voice display name '
                    . '(case-insensitive), e.g. "Aroha" (mi-NZ) or "Mathieu" (fr-FR), or "auto" to let the server '
                    . 'pick a voice matching the lesson language. If the language has no TTS voice, upload '
                    . 'model audio instead.',
                'example' => 'auto',
            ],
            'promptvoiceopt' => [
                'description' => 'Reading speed / processing option for the model TTS audio.',
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
            'hintrtl' => [
                'description' => 'Display the hints in right-to-left format (for hints written in an RTL language '
                    . 'such as Arabic or Hebrew).',
                'options' => [
                    ['value' => '0', 'meaning' => 'Left-to-right hints (default)'],
                    ['value' => '1', 'meaning' => 'Right-to-left hints'],
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
                . 'Only needed when the sentences have uploaded audio or images.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per set of sentences. The sentences should be in the lesson '
                . 'language at the learner\'s level, and short enough to say in one breath. Model TTS audio is '
                . 'generated automatically from the promptvoice; if the lesson language has no TTS voice, supply '
                . 'model audio for each sentence in the audio file area. Hints/translations after a pipe work '
                . 'well for meaning support.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::FILEANSWER . '1_audio',
                    'description' => 'Uploaded model audio for the sentences (overrides the promptvoice TTS audio). '
                        . 'Needed when the lesson language has no TTS voice.',
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
                        'type' => 'selfcheck',
                        'name' => 'Say and check',
                        'instructions' => 'Listen, then read each sentence aloud. Compare your recording with the '
                            . 'model and approve it when you are happy.',
                        'sentences' => [
                            'Kia ora.|Hello.',
                            'Kei te pēhea koe?|How are you?',
                            'Kei te pai ahau.|I am well.',
                        ],
                        'promptvoice' => 'auto',
                        'promptvoiceopt' => 'normal',
                    ],
                ],
            ],
        ];
    }

    /**
     * The key columns used by import, mapping each json/import name to its db column and data type.
     *
     * @return array
     */
    public static function get_keycolumns() {
        // Get the basic key columns and customize a little for instances of this item type.
        $keycols = parent::get_keycolumns();
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt', 'type' => 'voiceopts', 'optional' => true,
            'default' => null, 'dbname' => constants::POLLYOPTION];
        $keycols['text5'] = ['jsonname' => 'promptvoice', 'type' => 'voice', 'optional' => true,
            'default' => null, 'dbname' => constants::POLLYVOICE];
        $keycols['text1'] = ['jsonname' => 'sentences', 'type' => 'stringarray', 'optional' => true,
            'default' => [], 'dbname' => 'customtext1'];
        $keycols['int5'] = ['jsonname' => 'hidestartpage', 'type' => 'boolean', 'optional' => true,
            'default' => 0, 'dbname' => constants::GAPFILLHIDESTARTPAGE];
        $keycols['int7'] = ['jsonname' => 'hintrtl', 'type' => 'boolean', 'optional' => true,
            'default' => 0, 'dbname' => constants::FLUENCYHINTRTL];
        $keycols['fileanswer_audio'] = ['jsonname' => constants::FILEANSWER . '1_audio', 'type' => 'anonymousfile',
            'optional' => true, 'default' => null, 'dbname' => false];
        $keycols['fileanswer_image'] = ['jsonname' => constants::FILEANSWER . '1_image', 'type' => 'anonymousfile',
            'optional' => true, 'default' => null, 'dbname' => false];
        return $keycols;
    }

    /**
     * The prompt that the AI generate method requires.
     *
     * @param \stdClass $itemtemplate the item template
     * @param string $generatemethod extract, reuse or generate
     * @return string
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
                $prompt = "Generate a 1 dimensional array of 4 sentences in {language} suitable for {level} level "
                    . "learners on the topic of: [{topic}] ";
                break;
        }
        return $prompt;
    }

    /**
     * Prepare the item's result for the attempt review.
     *
     * @param stdClass $result the result for this item
     * @param stdClass $itemquizdata the item's exported quiz data
     */
    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $result->hascorrectanswer = true;
        $result->correctans = $itemquizdata->sentences;
        if (isset($result->resultsdata)) {
            $result->hasanswerdetails = true;
            $result->resultsdatajson = json_encode(
                $result->resultsdata,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            $result->resultstemplate = constants::M_COMPONENT . '/listitemresults';
        } else {
            $result->hasanswerdetails = false;
        }
    }
}
