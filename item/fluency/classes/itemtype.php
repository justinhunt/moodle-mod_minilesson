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

namespace minilessonitem_fluency;

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
    public static $skills = [constants::SKILL_SPEAKING, constants::SKILL_PRONUNCIATION];


    public const HIDEWARNING = 'customint6';

    // the item type
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

        // Is rtl
        $testitem->rtl = utils::is_rtl($this->language);
        $testitem->hintrtl = $this->itemrecord->{constants::FLUENCYHINTRTL} == 1;

        $testitem->readsentence = $this->itemrecord->{constants::READSENTENCE} == 1;
        $testitem->allowretry = $this->itemrecord->{constants::GAPFILLALLOWRETRY} == 1;
        $testitem->hidestartpage = $this->itemrecord->{constants::GAPFILLHIDESTARTPAGE} == 1;

        // Correct threshold.
        $testitem->correctthreshold = (int) $this->itemrecord->{constants::FLUENCYCORRECTTHRESHOLD};

        // Hide Warning
        $testitem->hidewarning = (int) $this->itemrecord->{self::HIDEWARNING};

        // Cloud Poodll.
        $maxtime = 0;
        $testitem = $this->set_cloudpoodll_details($testitem, $maxtime);
        // In the case of Norwegian, we set the language to Norwegian Bokmal for speech recognition.
        if ($testitem->language == 'no-NO') {
            $testitem->language = 'nb-NO';
        }

        // add a few things to enable the saving of uploaded audio (on S3)
        $testitem->savemedia = 1;
        $testitem->transcode = 1;
        $testitem->expiredays = 365;

        // MS token and region.
        $tokenobject = utils::fetch_msspeech_token($this->moduleinstance->region);
        if ($tokenobject) {
            $testitem->speechtoken = $tokenobject->token;
            $testitem->speechtokenvalidseconds = $tokenobject->validseconds;
            $testitem->speechtokentype = 'msspeech';
        } else {
            $testitem->speechtoken = false;
            $testitem->speechtokenvalidseconds = 0;
            $testitem->speechtokentype = '';
        }

        // We overwrite our regular poodll region with the MS region, eg useast1 becomes eastus, frankfurt becomes westeurope.
        $testitem->region = $tokenobject->region;
        $testitem->speechtokenregion = $tokenobject->region;
        $testitem->savemediaregion = $this->moduleinstance->region;

        // Build sentence objects.
        /* We do this right now so we get character level arrays. So  we can match mspeech per char results
        ultimately we want to do this in a way that suits fluency rather than piggy back on sgapfill. */
        $sentences = [];
        if (isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->customtext1);
        }

        $testitem->sentences = $this->process_spoken_sentences($sentences, []);
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

        $threshold = (int) ($newrecord->{constants::FLUENCYCORRECTTHRESHOLD} ?? 0);
        if ($threshold < 0 || $threshold > 100) {
            $error->col = constants::FLUENCYCORRECTTHRESHOLD;
            $error->message = get_string(
                'error:invalidoptionvalue',
                constants::M_COMPONENT,
                ['value' => $threshold, 'allowed' => '0-100']
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
        return 'A series of sentences the learner says aloud; the speech is scored for pronunciation accuracy '
            . 'and each part of the spoken sentence is marked up as good, fair or poor, with a pass threshold. '
            . 'Use it for focused pronunciation assessment of target sentences. It is stricter and more '
            . 'diagnostic than listenrepeat (listen-then-repeat practice) or speechcards (light drilling). '
            . 'Requires a lesson language with speech recognition support.';
    }

    /**
     * The agent-facing import field spec for fluency. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'fluency';

        $ownfields = [
            'sentences' => [
                'required' => true,
                'description' => 'The sentences to speak, as an array of strings, one per entry. A hint or '
                    . 'translation (shown to the learner) can follow the sentence after a pipe, '
                    . 'e.g. "Hello|Hola". Around 3 to 6 sentences per item works well.',
                'example' => '["Nice to meet you.|Encantado de conocerte.", "Where are you from?|¿De dónde eres?"]',
            ],
            'promptvoice' => [
                'description' => 'The TTS voice that provides model audio of each sentence. A voice display name '
                    . '(case-insensitive), e.g. "Joey" (en-US) or "Mathieu" (fr-FR), or "auto" to let the server '
                    . 'pick a voice matching the lesson language.',
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
            'correctthreshold' => [
                'description' => 'The pronunciation accuracy percentage (0-100) required to pass a sentence. '
                    . 'Set this explicitly: the authoring form default is 85, but the import default is 0.',
                'example' => '80',
            ],
            'hidewarning' => [
                'description' => 'How the "almost correct" level is shown in the spoken-sentence markup.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Mark words as good (green), fair (orange) or poor (red) (default)'],
                    ['value' => '1', 'meaning' => 'Only good (green) and poor (red); no orange level'],
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
                . 'language at the learner\'s level, and short enough to say in one breath. Set correctthreshold '
                . '(around 80-85) to control how strict the pass mark is. Model TTS audio is generated '
                . 'automatically from the promptvoice; hints/translations after a pipe work well for meaning support.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::FILEANSWER . '1_audio',
                    'description' => 'Uploaded model audio for the sentences (overrides the promptvoice TTS audio). '
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
                        'type' => 'fluency',
                        'name' => 'Say the sentences',
                        'instructions' => 'Read the sentences aloud. Click the microphone icon to start recording.',
                        'sentences' => [
                            'He is visiting China.',
                            'He is building a house.',
                            'He is on a video call.',
                        ],
                        'promptvoice' => 'auto',
                        'promptvoiceopt' => 'normal',
                        'correctthreshold' => 80,
                    ],
                ],
            ],
        ];
    }

    /*
    * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
    */
    public static function get_keycolumns() {
        // Get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt', 'type' => 'voiceopts', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYOPTION];
        $keycols['text5'] = ['jsonname' => 'promptvoice', 'type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYVOICE];
        $keycols['int3'] = ['jsonname' => 'correctthreshold', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::FLUENCYCORRECTTHRESHOLD];
        $keycols['text1'] = ['jsonname' => 'sentences', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => 'customtext1'];
        $keycols['int5'] = ['jsonname' => 'hidestartpage', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::GAPFILLHIDESTARTPAGE];
        $keycols['int6'] = ['jsonname' => 'hidewarning', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => self::HIDEWARNING];
        $keycols['int7'] = ['jsonname' => 'hintrtl', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::FLUENCYHINTRTL];
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
        if (isset($result->resultsdata)) {
            $result->hasanswerdetails = true;
            $result->resultsdatajson = json_encode(
                $result->resultsdata,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            // For now ...
            $result->resultstemplate = constants::M_COMPONENT . '/listitemresults';
        } else {
            $result->hasanswerdetails = false;
        }
    }

}
