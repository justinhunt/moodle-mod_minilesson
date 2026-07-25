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

namespace minilessonitem_listeninggapfill;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use stdClass;

/**
 * Renderable class for a listening gap fill item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item
{
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_LISTENING, constants::SKILL_WRITING];

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

    public const ENABLEVKEYBOARD = 'customtext6';
    public const CUSTOMKEYS = 'customtext7';

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

        // Sentences.
        $sentences = [];
        if (isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->customtext1);
        }

        $testitem->sentences = $this->process_listeninggapfill_sentences($sentences);

        // If shuffle order is on, randomize the order the sentences are delivered in.
        // The image/audio and gap data are already bound to each sentence object, so they travel with it.
        // We only renumber index/indexplusone so the DOM order stays in sync with the JS pointer.
        if (!empty($this->itemrecord->{constants::GAPFILLSHUFFLEORDER})) {
            shuffle($testitem->sentences);
            foreach ($testitem->sentences as $newindex => $thesentence) {
                $thesentence->index = $newindex;
                $thesentence->indexplusone = $newindex + 1;
            }
        }

        $testitem->hintrtl = $this->itemrecord->{constants::GAPFILLHINTRTL} == 1;
        $testitem->allowretry = $this->itemrecord->{constants::GAPFILLALLOWRETRY} == 1;
        $testitem->hidestartpage = $this->itemrecord->{constants::GAPFILLHIDESTARTPAGE} == 1;
        $enablevkeyboard = $this->itemrecord->{self::ENABLEVKEYBOARD};
        $customkeys = $this->itemrecord->{self::CUSTOMKEYS};

        // If compact layout selected (2), we fetch the keys and set to custom layout (2) for JS
        if ($enablevkeyboard == 2) {
            $testitem->enablevkeyboard = 2;
            $testitem->customkeys = \mod_minilesson\utils::get_compact_keys($this->moduleinstance->ttslanguage);
        } elseif ($enablevkeyboard == 3) {
            // If custom layout selected (3), we set to custom layout (2) for JS
            $testitem->enablevkeyboard = 2;
            $testitem->customkeys = $customkeys;
        } else {
            $testitem->enablevkeyboard = $enablevkeyboard;
            $testitem->customkeys = $customkeys;
        }

        // Cloudpoodll
        $testitem = $this->set_cloudpoodll_details($testitem);
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
        if (!preg_match('/\[[^\]]+\]/', (string) $newrecord->customtext1)) {
            $error->col = 'customtext1';
            $error->message = get_string('error:nogaps', constants::M_COMPONENT);
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
        return 'A set of sentences each with letters gapped out; the learner hears each sentence read aloud by '
            . 'TTS and types the missing letters. Use it for listening + spelling/vocabulary practice, e.g. '
            . 'catching key words from spoken sentences. For gapfills with no audio use typinggapfill; for '
            . 'spoken responses use speakinggapfill; for a whole passage use passagegapfill.';
    }

    /**
     * The agent-facing import field spec for listeninggapfill. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'listeninggapfill';

        $fields += static::aigen_gapfill_shared_field_specs();

        $ownfields = [
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
                ],
            ],
            'enablevkeyboard' => [
                'description' => 'On-screen virtual keyboard for typing accented/special characters.',
                'options' => [
                    ['value' => '0', 'meaning' => 'No virtual keyboard (default)'],
                    ['value' => '1', 'meaning' => 'Full keyboard layout for the lesson language'],
                    ['value' => '2', 'meaning' => 'Compact layout: just the special characters of the lesson language'],
                    ['value' => '3', 'meaning' => 'Custom keys, supplied in customkeys'],
                ],
            ],
            'customkeys' => [
                'description' => 'Space-separated characters for the custom virtual keyboard (enablevkeyboard=3).',
                'example' => 'á é í ó ú ü ñ',
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
                . 'In each sentence enclose the letters to be typed in square brackets. The learner relies on '
                . 'the audio to fill the gaps, so gap words that are clearly audible. TTS audio is generated '
                . 'automatically from the promptvoice.',
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
                        'type' => 'listeninggapfill',
                        'name' => 'Listening gapfill',
                        'instructions' => 'Listen to the audio and fill in the gaps to complete the sentences.',
                        'sentences' => [
                            'They go to [church] on Sundays.',
                            'A farmer in the [field] tries to help them.',
                            'They make a c[ircle] with their hands.',
                        ],
                        'promptvoice' => 'auto',
                        'promptvoiceopt' => 'normal',
                        'allowretry' => 'yes',
                        'timelimit' => 20,
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
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt', 'type' => 'voiceopts', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYOPTION];
        $keycols['text5'] = ['jsonname' => 'promptvoice', 'type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYVOICE];
        $keycols['int3'] = ['jsonname' => 'allowretry', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::GAPFILLALLOWRETRY];
        $keycols['int1'] = ['jsonname' => 'shuffleorder', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::GAPFILLSHUFFLEORDER];
        $keycols['text1'] = ['jsonname' => 'sentences', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => 'customtext1'];
        $keycols['text6'] = ['jsonname' => 'enablevkeyboard', 'type' => 'string', 'optional' => true, 'default' => 0, 'dbname' => self::ENABLEVKEYBOARD];
        $keycols['text7'] = ['jsonname' => 'customkeys', 'type' => 'string', 'optional' => true, 'default' => '', 'dbname' => self::CUSTOMKEYS];
        $keycols['int5'] = ['jsonname' => 'hidestartpage', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::GAPFILLHIDESTARTPAGE];
        $keycols['int6'] = ['jsonname' => 'hintrtl', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::GAPFILLHINTRTL];
        $keycols['fileanswer_audio'] = ['jsonname' => constants::FILEANSWER . '1_audio', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        $keycols['fileanswer_image'] = ['jsonname' => constants::FILEANSWER . '1_image', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        return $keycols;
    }

    /*
    * This function return the prompt that the generate method requires for listening gap fill items.
    */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod)
    {
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
