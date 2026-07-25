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

namespace mod_minilesson\local\itemtype;

use core_plugin_manager;
use mod_minilesson\constants;
use mod_minilesson\plugininfo\minilessonitem;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for an item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class item implements \templatable, \renderable {

    // the item type
    public const ITEMTYPE = '';

    /** @var bool force titles */
    protected $forcetitles;

    /** @var array editor optiosns */
    protected $editoroptions;

    /** @var array filemanager options */
    protected $filemanageroptions;

    /** @var int $id The current number in the lesson/container */
    protected $currentnumber;

    /** @var \stdClass $itemrecord The db record for the item. */
    protected $itemrecord;

    /** @var \stdClass $context The mod context. */
    protected $context;

    /** @var \stdClass $course The mod context. */
    protected $course;


    /** @var string $token The cloudpoodll token. */
    protected $token;

    /** @var string $region The aws region. */
    protected $region;

    /** @var string $language The target language. */
    protected $language;

    /** @var \stdClass $moduleinstance The module. */
    protected $moduleinstance;

    // NEEDS SPEECH REC
    protected $needsspeechrec = false;

    /** @var bool whether this item type produces a grade/result. */
    public $gradeable = true;

    /** @var bool show itemreview */
    protected $showitemreview = true;

    /**
     * The class constructor.
     *
     */
    public function __construct($itemrecord, $moduleinstance = false, $context = false) {
        $this->from_record($itemrecord, $moduleinstance, $context);
    }

    /**
     * The class constructor.
     *
     * @param int $currentnumber The current number in the lesson
     * @param \stdClass $itemrecord The db record for the item.
     */
    /*
     public static function from_id($itemid,$moduleinstance=false, $context=false) {
     global $DB;
     $itemrecord = $DB->get_record(constants::M_QTABLE,['id'=>$itemid],'*', MUST_EXIST);
     $instance=self::from_record($itemrecord,$moduleinstance,$context);
     return $instance;
     }
     */

    /**
     * The class constructor.
     *
     * @param int $currentnumber The current number in the lesson
     * @param \stdClass $itemrecord The db record for the item.
     */
    public function from_record($itemrecord, $moduleinstance = false, $context = false) {
        global $DB;

        $this->itemrecord = $itemrecord;
        if (!$moduleinstance) {
            $this->moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $this->itemrecord->minilesson], '*', MUST_EXIST);
        } else {
            $this->moduleinstance = $moduleinstance;
        }
        $this->course = get_course($this->moduleinstance->course);
        if (!$context) {
            $cm = get_coursemodule_from_instance('minilesson', $this->moduleinstance->id, $this->course->id, false, MUST_EXIST);
            $this->context = \context_module::instance($cm->id);
        } else {
            $this->context = $context;
        }
        if (!empty($token)) {
            $this->token = $token;
        }
        $this->editoroptions = self::fetch_editor_options($this->course, $this->context);
        $this->filemanageroptions = self::fetch_filemanager_options($this->course);
        $this->forcetitles = $this->moduleinstance->showqtitles;
        $this->region = $this->moduleinstance->region;
        $this->language = $this->moduleinstance->ttslanguage;
        $this->showitemreview = $this->moduleinstance->showitemreview;
    }

    /*
     * Returns the itemtype class for the itemtype
     */
    public static function get_itemtype_class($itemtype) {
        $classname = '\\minilessonitem_' . $itemtype . '\\itemtype';
        if (class_exists($classname)) {
            return $classname;
        } else {
            return false;
        }
    }

    public function upgrade_item($oldversion) {
        // This is a placeholder for any upgrade logic that might be needed.
        // Each item will implement its own upgrade logic if needed.
        return true;
    }


    public function set_token($token) {
        $this->token = $token;
    }
    public function set_currentnumber($currentnumber) {
        $this->currentnumber = $currentnumber;
    }

    public static function get_keycolumns() {
        $keycolumns = [];
        $keycolumns['type'] = ['type' => 'string', 'optional' => false, 'default' => '', 'dbname' => 'type'];

        // This is a special case. We don't store the media file(s) in the db or even the draft id. We just put it in the files area.
        // There could be more than one. ie an audio, a video and a picture.
        $keycolumns[constants::MEDIAQUESTION] = ['type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        $keycolumns[constants::AUDIOSTORY] = ['type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];

        $keycolumns['name'] = ['type' => 'string', 'optional' => false, 'default' => '', 'dbname' => 'name'];
        $keycolumns['visible'] = ['type' => 'boolean', 'optional' => true, 'default' => 1, 'dbname' => 'visible'];
        $keycolumns['instructions'] = ['type' => 'string', 'optional' => true, 'default' => '', 'dbname' => 'iteminstructions'];
        $keycolumns['text'] = ['type' => 'string', 'optional' => true, 'default' => '', 'dbname' => 'itemtext'];
        $keycolumns['textformat'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'itemtextformat'];
        $keycolumns['tts'] = ['type' => 'string', 'optional' => true, 'default' => '', 'dbname' => 'itemtts'];
        $keycolumns['ttsvoice'] = ['type' => 'voice', 'optional' => true, 'default' => '', 'dbname' => 'itemttsvoice'];
        $keycolumns['ttsoption'] = ['type' => 'voiceopts', 'optional' => true, 'default' => 0, 'dbname' => 'itemttsoption'];
        $keycolumns['ttsautoplay'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'itemttsautoplay'];
        $keycolumns['textarea'] = ['type' => 'string', 'optional' => true, 'default' => '', 'dbname' => 'itemtextarea'];
        $keycolumns['iframe'] = ['type' => 'string', 'optional' => true, 'default' => '', 'dbname' => constants::MEDIAIFRAME];
        $keycolumns['ytid'] = ['type' => 'string', 'optional' => true, 'default' => '', 'dbname' => 'itemytid'];
        $keycolumns['ytstart'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'itemytstart'];
        $keycolumns['ytend'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'itemytend'];
        $keycolumns['audiofname'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'itemaudiofname'];
        $keycolumns['audiostoryzoom'] = ['type' => 'int', 'optional' => true, 'default' => 1, 'dbname' => 'itemaudiostoryzoom'];
        $keycolumns['ttsdialog'] = ['type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => 'itemttsdialog'];
        $keycolumns['ttsdialogopts'] = ['type' => 'stringjson', 'optional' => true, 'default' => null, 'dbname' => 'itemttsdialogopts'];
        $keycolumns['ttsdialogvoicea'] = ['type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::TTSDIALOGVOICEA];
        $keycolumns['ttsdialogvoiceb'] = ['type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::TTSDIALOGVOICEB];
        $keycolumns['ttsdialogvoicec'] = ['type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::TTSDIALOGVOICEC];
        $keycolumns['ttsdialoglabela'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => constants::TTSDIALOGLABELA];
        $keycolumns['ttsdialoglabelb'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => constants::TTSDIALOGLABELB];
        $keycolumns['ttsdialoglabelc'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => constants::TTSDIALOGLABELC];
        $keycolumns['ttsdialogvisible'] = ['type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::TTSDIALOGVISIBLE];
        $keycolumns['ttspassage'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'itemttspassage'];
        $keycolumns['ttspassageopts'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'itemttspassageopts'];
        $keycolumns['ttspassagevoice'] = ['type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::TTSPASSAGEVOICE];
        $keycolumns['ttspassagespeed'] = ['type' => 'voiceopts', 'optional' => true, 'default' => null, 'dbname' => constants::TTSPASSAGESPEED];
        $keycolumns['text1'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'customtext1'];
        $keycolumns['text1format'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customtext1format'];
        $keycolumns['text2'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'customtext2'];
        $keycolumns['text2format'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customtext2format'];
        $keycolumns['text3'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'customtext3'];
        $keycolumns['text3format'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customtext3format'];
        $keycolumns['text4'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'customtext4'];
        $keycolumns['text4format'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customtext4format'];
        $keycolumns['text5'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'customtext5'];
        $keycolumns['text5format'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customtext5format'];
        $keycolumns['text6'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'customtext6'];
        $keycolumns['data1'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'customdata1'];
        $keycolumns['data2'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'customdata2'];
        $keycolumns['data3'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'customdata3'];
        $keycolumns['data4'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'customdata4'];
        $keycolumns['data5'] = ['type' => 'string', 'optional' => true, 'default' => null, 'dbname' => 'customdata5'];
        $keycolumns['int1'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint1'];
        $keycolumns['int2'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint2'];
        $keycolumns['int3'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint3'];
        $keycolumns['int4'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint4'];
        $keycolumns['int5'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint5'];
        $keycolumns['int6'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint6'];
        $keycolumns['int7'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint7'];
        $keycolumns['int8'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint8'];
        $keycolumns['int9'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint9'];
        $keycolumns['int10'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint10'];
        $keycolumns['int11'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint11'];
        $keycolumns['int12'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint12'];
        $keycolumns['int13'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint13'];
        $keycolumns['int14'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint14'];
        $keycolumns['int15'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'customint15'];
        $keycolumns['timelimit'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'timelimit'];
        $keycolumns['layout'] = ['type' => 'layout', 'optional' => true, 'default' => 0, 'dbname' => 'layout'];
        $keycolumns['correctanswer'] = ['type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => 'correctanswer'];
        $keycolumns['nativelangchooser'] = ['type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::NATIVELANGCHOOSER];

        foreach ($keycolumns as $key => $keycol) {
            $keycolumns[$key]['jsonname'] = $key;
        }
        return $keycolumns;
    }

    public static function get_import_keycolumns() {
        $keycols = [];
        return $keycols;
    }

    /*
     * Validates the items data from the import record
     * most types should override this with specific validation code
     */
    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';
        // check for errors here

        // return false to indicate no error
        return false;
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the item
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        $testitem = new \stdClass();
        $testitem->itemimageurl = $this->get_plugininfo()->get_logo_url()->out(false);
        $testitem->templatename = $this->get_template_name($output);
        $testitem = $this->get_common_elements($testitem);
        $testitem = $this->get_text_answer_elements($testitem);

        return $testitem;
    }


    /*
     This function return the fileareas that the generate method will put files into
     */
    public static function aigen_fetch_fileareas($itemtemplate, $thefiles) {
        $fileareas = [];
        if (isset($itemtemplate['filesid']) && !empty($itemtemplate['filesid'])) {
            $filesid = $itemtemplate['filesid'];
            if (isset($thefiles[$filesid])) {
                foreach ($thefiles[$filesid] as $filearea => $files) {
                    $fileareas[] = $filearea;
                }
            }
        }
        return $fileareas;
    }

    /*
     This function return the fields that the generate method will generate
     */
    public static function aigen_fetch_placeholders($itemtemplate) {
        $allfields = static::get_keycolumns();
        $placeholderfields = [];
        $ignorefields = ['type', 'name', 'instructions', 'audiofname'];
        foreach ($allfields as $key => $field) {
            if (in_array($field['jsonname'], $ignorefields)) {
                continue; // Skip fields that are not placeholders.
            }

            $isset = false;
            if (isset($itemtemplate[$field['jsonname']])) {
                switch ($field['type']) {
                    case 'string':
                    case 'int':
                        $isset = $itemtemplate[$field['jsonname']] !== $field['default'];
                        break;
                    case 'boolean':
                        $isset = $itemtemplate[$field['jsonname']] !== ($field['default'] ? 'yes' : 'no');
                        break;
                    case 'voice':
                    case 'voiceopts':
                    case 'layout':
                        // we dont replace these
                        break;

                    case 'stringarray':
                        if (is_array($itemtemplate[$field['jsonname']]) && count($itemtemplate[$field['jsonname']]) > 0) {
                            foreach ($itemtemplate[$field['jsonname']] as $value) {
                                if (!empty($value)) {
                                    $isset = true;
                                    break;
                                }
                            }
                        }
                        break;

                    case 'anonymousfile':
                        // Files are handled elsewhere
                        break;

                    default:
                        // Other types can be added as needed.
                }
            }
            if ($isset) {
                $placeholderfields[] = $field['jsonname'];
            }
        }

        // At this point we have all the fields that are not the default value.
        // But most of these are not going to be generated.
        /*
         foreach($placeholderfields as $index =>$thefield) {
         switch($thefield) {
         case 'ttspassage':
         case 'ttspassageopts':
         case 'ttspassagevoice':
         case 'ttspassagespeed':
         break;
         case 'audiofname':
         break;
         case 'type':
         case 'name':
         case 'visible':
         case 'instructions':
         case 'text':
         case 'textformat':
         case 'tts':
         case 'ttsvoice':
         case 'ttsoption':
         case 'ttsautoplay':
         case 'textarea':
         case 'iframe':
         case 'ytid':
         case 'ytstart':
         case 'ytend':
         case 'ttsdialog':
         case 'ttsdialogopts':
         case 'ttsdialogvoicea':
         case 'ttsdialogvoiceb':
         case 'ttsdialogvoicec':
         case 'ttsdialogvisible':
         default:
         unset($placeholderfields[$index]);
         }
         }
         */
        return $placeholderfields;
    }

    /*
     This function return the prompt that the generate method requires.
     */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Extract 4 sentences from the following {language} text: [{text}]. " . PHP_EOL .
                    "The sentences should be suitable for {level} level learners. ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Generate a passage of text in {language} suitable for {level} level learners on the topic of: [{topic}] " .
                    "The passage should take about 1 minute to read aloud. " . PHP_EOL .
                    "The passage should be engaging and appropriate for the target audience.";
                break;
        }
        return $prompt;
    }

    /**
     * Describes the shared files/filesid convention of the import payload. The web service layer
     * appends this to the usage guidance of every item type that documents its import spec.
     */
    public const AIGEN_FILES_CONVENTION = 'Files (images/audio) are embedded in the import payload as base64. '
        . 'Give the item a numeric "filesid" property, and add a top level "files" object to the payload: '
        . '{"files": {"<filesid>": {"<filearea>": {"<filename>": "<base64 data>"}}}}. '
        . 'The file areas accepted by each item type are listed in its import spec. '
        . 'Prefer the tts fields over uploading audio files: tts audio is generated automatically by the server.';

    /**
     * A short description of when and why to choose this item type when composing a lesson.
     * Item types should override this. Used by the aigen web services (agent-facing).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return '';
    }

    /**
     * The machine readable import field spec for this item type, used by AI agents composing
     * import JSON for the mod_minilesson_aigen_import_items_json web service.
     * Returns null when the item type has not (yet) documented its import format.
     * Shape: ['usage' => string, 'fields' => [fieldspec, ...], 'fileareas' => [...], 'example' => [...]]
     * where a fieldspec is ['jsonname', 'type', 'required', 'default', 'description', 'options'?, 'example'?]
     * and 'example' is a complete import payload as a PHP array (items + optionally files).
     * Item types document themselves by overriding this; see minilessonitem_multichoice for a model.
     *
     * @return array|null the import spec, or null when this item type has no agent-facing import docs
     */
    public static function aigen_fetch_import_spec() {
        return null;
    }

    /**
     * Builds field specs (keyed by jsonname) for the requested common/shared import fields.
     * type/required/default are read from get_keycolumns() so they cannot drift from the import code;
     * the descriptions and option meanings are maintained here.
     * Item type overrides of aigen_fetch_import_spec() call this for the shared fields they support,
     * may tweak the descriptions, then append their own field specs.
     *
     * @param array $jsonnames the jsonnames of the shared fields to build specs for
     * @return array field specs keyed by jsonname
     */
    protected static function aigen_common_import_field_specs($jsonnames) {
        $keycolsbyjsonname = static::aigen_keycolumns_by_jsonname();

        $voicenote = 'A voice display name (case-insensitive), e.g. "Joey" (en-US) or "Mathieu" (fr-FR), '
            . 'or "auto" to let the server pick a voice matching the lesson language.';
        $catalog = [
            'type' => [
                'description' => 'The item type machine name, e.g. "multichoice".',
            ],
            'name' => [
                'description' => 'A short title for the item. Shown in the lesson item list and at the top of the item screen.',
                'example' => 'Comprehension check 1',
            ],
            'visible' => [
                'description' => 'Whether the item is shown to learners.',
                'options' => [
                    ['value' => '1', 'meaning' => 'Visible (default)'],
                    ['value' => '0', 'meaning' => 'Hidden from learners'],
                ],
            ],
            'instructions' => [
                'description' => 'One short sentence telling the learner what to do, shown near the top of the item.',
                'example' => 'Choose the correct answer.',
            ],
            'text' => [
                'description' => 'The main text of the item, usually the question. It normally renders as a short, '
                    . 'centered heading-style line above the activity, so keep it to a single line: multi-line '
                    . 'content such as a two-speaker dialog looks poor centered. For multi-line or dialog content '
                    . 'use the "textarea" text block instead, where the item type offers it. Its exact role '
                    . 'depends on the item type (see the item type usage notes).',
            ],
            'tts' => [
                'description' => 'Text read aloud to the learner as a text-to-speech (TTS) audio prompt at the top of the item. '
                    . 'Use this to add an audio prompt without uploading audio files.',
                'example' => 'Which of these expressions is a greeting?',
            ],
            'ttsvoice' => [
                'description' => 'The TTS voice that reads the "tts" text. ' . $voicenote,
                'example' => 'auto',
            ],
            'ttsoption' => [
                'description' => 'Reading speed / processing option for the "tts" audio.',
                'options' => [
                    ['value' => 'normal', 'meaning' => 'Normal speed (default; any unrecognised value also maps to normal)'],
                    ['value' => 'slow', 'meaning' => 'Slow reading speed'],
                    ['value' => 'veryslow', 'meaning' => 'Very slow reading speed'],
                    ['value' => 'SSML', 'meaning' => 'Treat the tts text as SSML markup (this value is case-sensitive)'],
                ],
            ],
            'ttsautoplay' => [
                'description' => 'Whether the "tts" audio plays automatically when the item is shown.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Learner presses play (default)'],
                    ['value' => '1', 'meaning' => 'Play automatically'],
                ],
            ],
            'ttsdialog' => [
                'description' => 'A two or three speaker dialog read aloud by TTS. An array of lines; start each line with '
                    . '"A)", "B)" or "C)" to assign it to the matching speaker voice (ttsdialogvoicea/b/c). '
                    . 'Lines without a prefix continue the previous speaker. A line starting with ">>" plays a named sound effect.',
                'example' => '["A) Hello, how are you?", "B) Fine thanks. And you?"]',
            ],
            'ttsdialogvoicea' => [
                'description' => 'TTS voice for dialog speaker A. ' . $voicenote,
                'example' => 'auto',
            ],
            'ttsdialogvoiceb' => [
                'description' => 'TTS voice for dialog speaker B. ' . $voicenote,
                'example' => 'auto',
            ],
            'ttsdialogvoicec' => [
                'description' => 'TTS voice for dialog speaker C. ' . $voicenote,
                'example' => 'auto',
            ],
            'ttsdialoglabela' => [
                'description' => 'Display label shown above dialog speaker A\'s lines (only used when ttsdialogvisible=1). '
                    . 'Leave blank to use the default "Speaker A".',
                'example' => 'Waiter',
            ],
            'ttsdialoglabelb' => [
                'description' => 'Display label shown above dialog speaker B\'s lines (only used when ttsdialogvisible=1). '
                    . 'Leave blank to use the default "Speaker B".',
                'example' => 'Customer',
            ],
            'ttsdialoglabelc' => [
                'description' => 'Display label shown above dialog speaker C\'s lines (only used when ttsdialogvisible=1). '
                    . 'Leave blank to use the default "Speaker C".',
                'example' => 'Waiter 2',
            ],
            'ttsdialogvisible' => [
                'description' => 'Whether the dialog text is shown to the learner.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Audio only, dialog text is hidden (default)'],
                    ['value' => '1', 'meaning' => 'Show the dialog text'],
                ],
            ],
            'textarea' => [
                'description' => 'A rich text content block shown within the item content area, left-aligned with '
                    . 'line breaks preserved (HTML or plain text). Prefer this over "text" for anything longer than '
                    . 'a single line - a reading passage, or a dialog with speaker turns - which renders poorly in '
                    . 'the centered "text" heading.',
            ],
            'ttspassage' => [
                'description' => 'A reading passage displayed sentence by sentence, each sentence with its own '
                    . 'TTS audio player so the learner can listen while reading. Use this (rather than "tts") '
                    . 'for longer passages. Blank lines become paragraph breaks.',
                'example' => 'The sun rises in the east. In the evening it sets in the west.',
            ],
            'ttspassagevoice' => [
                'description' => 'The TTS voice that reads the ttspassage sentences. ' . $voicenote,
                'example' => 'auto',
            ],
            'ttspassagespeed' => [
                'description' => 'Reading speed for the ttspassage audio.',
                'options' => [
                    ['value' => 'normal', 'meaning' => 'Normal speed (default; any unrecognised value also maps to normal)'],
                    ['value' => 'slow', 'meaning' => 'Slow reading speed'],
                    ['value' => 'veryslow', 'meaning' => 'Very slow reading speed'],
                ],
            ],
            'ytid' => [
                'description' => 'A YouTube video to show as the item media prompt: a video id (e.g. "dQw4w9WgXcQ") '
                    . 'or a full YouTube URL.',
                'example' => 'dQw4w9WgXcQ',
            ],
            'ytstart' => [
                'description' => 'Start point of the YouTube clip, in seconds from the beginning of the video.',
                'example' => '30',
            ],
            'ytend' => [
                'description' => 'End point of the YouTube clip, in seconds from the beginning of the video. '
                    . '0 = play to the end.',
                'example' => '90',
            ],
            'iframe' => [
                'description' => 'Raw iframe/embed code to show as the item media prompt. '
                    . 'Prefer ytid for YouTube videos and the tts fields for audio.',
            ],
            'audiofname' => [
                'description' => 'Audio story image entry times: one HH:MM:SS timestamp per line, in image order, '
                    . 'giving the point in the narration at which each numbered image (1.<ext>, 2.<ext>, ... in the '
                    . constants::AUDIOSTORY . ' file area) should appear. Only used when the item is an audio story.',
                'example' => "00:00:00\n00:00:10\n00:00:20",
            ],
            'audiostoryzoom' => [
                'description' => 'Ken Burns style zoom and pan effect applied to the audio story images.',
                'options' => [
                    ['value' => (string) constants::ZOOMANDPAN_NONE, 'meaning' => 'No zoom or pan'],
                    ['value' => (string) constants::ZOOMANDPAN_LITE, 'meaning' => 'Subtle zoom and pan (default)'],
                    ['value' => (string) constants::ZOOMANDPAN_MEDIUM, 'meaning' => 'Medium zoom and pan'],
                    ['value' => (string) constants::ZOOMANDPAN_MORE, 'meaning' => 'Strong zoom and pan'],
                ],
            ],
            'nativelangchooser' => [
                'description' => 'Whether to show a native language chooser on the item. When enabled the learner '
                    . 'picks their first language, and that choice is remembered and reused for translations, '
                    . 'definitions and feedback elsewhere in the activity. Accepts yes/no (or 1/0).',
                'options' => [
                    ['value' => '0', 'meaning' => 'No native language chooser (default)'],
                    ['value' => '1', 'meaning' => 'Show the native language chooser'],
                ],
            ],
            'timelimit' => [
                'description' => 'Time limit for the item in seconds. 0 = no time limit.',
                'example' => '60',
            ],
            'layout' => [
                'description' => 'Position of the media/prompt area relative to the item content.',
                'options' => [
                    ['value' => 'horizontal', 'meaning' => 'Media beside the content'],
                    ['value' => 'vertical', 'meaning' => 'Media above the content'],
                    ['value' => 'magazine', 'meaning' => 'Magazine style layout'],
                    ['value' => 'auto', 'meaning' => 'Automatic (default; any unrecognised value also maps to auto)'],
                ],
            ],
        ];

        $specs = [];
        foreach ($jsonnames as $jsonname) {
            // Skip a requested field that has no prose entry, or that this item type does not have as a
            // keycolumn (e.g. a subtype that removed a base column) - these are legitimate absences.
            if (!isset($catalog[$jsonname]) || !isset($keycolsbyjsonname[$jsonname])) {
                continue;
            }
            $specs[$jsonname] = static::aigen_seed_field_spec($jsonname, $catalog[$jsonname], $keycolsbyjsonname);
        }
        return $specs;
    }

    /**
     * Builds a map of the item type's keycolumns keyed by their jsonname (import field name).
     *
     * @return array jsonname => keycolumn definition
     */
    protected static function aigen_keycolumns_by_jsonname() {
        $map = [];
        foreach (static::get_keycolumns() as $keycol) {
            $map[$keycol['jsonname']] = $keycol;
        }
        return $map;
    }

    /**
     * Builds the field specs (keyed by jsonname) shared by the sentence gapfill family
     * (typinggapfill, listeninggapfill, speakinggapfill). Each type appends its own fields
     * and may tweak these descriptions.
     *
     * @return array field specs keyed by jsonname
     */
    protected static function aigen_gapfill_shared_field_specs() {
        $keycolsbyjsonname = static::aigen_keycolumns_by_jsonname();
        $specs = [];
        $shared = [
            'sentences' => [
                'required' => true,
                'description' => 'The gapfill sentences, as an array of strings, one sentence per entry. '
                    . 'In each sentence enclose the gap letters in square brackets - a whole word ("The [dog] barks") '
                    . 'or part of a word ("This is my d[og]"). A hint can follow the sentence after a pipe, '
                    . 'e.g. "This is my d[og]|a common pet". Hints can be in the learner\'s native language.',
                'example' => '["Can you play any musical in[struments]?|things like guitars and pianos"]',
            ],
            'allowretry' => [
                'description' => 'Whether the learner can submit new attempts when their response was not correct.',
                'options' => [
                    ['value' => '0', 'meaning' => 'One attempt (default)'],
                    ['value' => '1', 'meaning' => 'Allow retries'],
                ],
            ],
            'shuffleorder' => [
                'description' => 'Whether the sentence order is randomized for each learner. Each sentence keeps '
                    . 'its matching image and audio.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Keep the authored order (default)'],
                    ['value' => '1', 'meaning' => 'Shuffle the sentence order'],
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
        foreach ($shared as $jsonname => $overlay) {
            // A gapfill subtype that does not have this shared keycolumn simply omits it.
            if (!isset($keycolsbyjsonname[$jsonname])) {
                continue;
            }
            $specs[$jsonname] = static::aigen_seed_field_spec($jsonname, $overlay, $keycolsbyjsonname);
        }
        return $specs;
    }

    /**
     * Builds one field spec for the agent-facing import spec: type/required/default are seeded
     * from get_keycolumns() (looked up by jsonname), then the overlay (description/options/example,
     * and optionally a required override) is merged on top.
     *
     * A per-type override's own-field loop passes jsonnames it knows exist, so an unknown jsonname
     * (a typo or a renamed keycolumn) is a coding error and throws - otherwise a null would reach the
     * external structure and make the whole item type's details unfetchable. The shared helpers that
     * may legitimately request an absent field membership-check before calling this.
     *
     * @param string $jsonname the import field name as used in get_keycolumns
     * @param array $overlay description/options/example (and optionally a required override)
     * @param array|null $keycolsbyjsonname prebuilt jsonname => keycolumn map (built here when null)
     * @return array the field spec
     * @throws \coding_exception if the jsonname is not a keycolumn of this item type
     */
    protected static function aigen_seed_field_spec($jsonname, $overlay, ?array $keycolsbyjsonname = null) {
        if ($keycolsbyjsonname === null) {
            $keycolsbyjsonname = static::aigen_keycolumns_by_jsonname();
        }
        if (!isset($keycolsbyjsonname[$jsonname])) {
            throw new \coding_exception(
                'aigen import spec references unknown import field "' . $jsonname . '" for ' . static::class
            );
        }
        $keycol = $keycolsbyjsonname[$jsonname];
        return array_merge([
            'jsonname' => $jsonname,
            'type' => $keycol['type'],
            'required' => empty($keycol['optional']),
            'default' => self::aigen_stringify_value($keycol['default']),
        ], $overlay);
    }

    /**
     * Renders a keycolumn default (which may be null, an array, a bool etc) as the string
     * representation used in the agent-facing import spec.
     *
     * @param mixed $value the keycolumn default
     * @return string
     */
    protected static function aigen_stringify_value($value) {
        if ($value === null) {
            return '';
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return (string) $value;
    }

    /**
     * Builds the prompt for the AI helper in the code editor.
     *
     * @param string $language The language of the code (e.g., 'yarn', 'markdown', 'html').
     * @param string $prompt The user's prompt.
     * @param string $currentcode The current code in the editor.
     * @return string The full prompt for the AI.
     */
    public static function codeeditor_build_prompt($language, $prompt, $currentcode) {
        $fullprompt = "You are an assistant helping a teacher create or edit educational content." . PHP_EOL;
        $fullprompt .= "The current language/format is: " . $language . PHP_EOL;
        if (!empty($currentcode)) {
            $fullprompt .= "The existing code is:" . PHP_EOL . "---" . PHP_EOL . $currentcode . PHP_EOL . "---" . PHP_EOL;
            $fullprompt .= "Please modify the existing code based on this instruction: " . $prompt . PHP_EOL;
        } else {
            $fullprompt .= "Please create new code based on this instruction: " . $prompt . PHP_EOL;
        }
        $fullprompt .= "Only return the code itself, without any explanations or markdown code blocks unless they are part of the content.";
        return $fullprompt;
    }

    protected function get_common_elements($testitem) {
        global $CFG;

        $itemrecord = $this->itemrecord;
        $editoroptions = $this->editoroptions;
        // remove what we don't need for format_text (M44 complains in format_text)
        unset($editoroptions['trusttext']);
        unset($editoroptions['subdirs']);
        unset($editoroptions['maxfiles']);
        unset($editoroptions['maxbytes']);

        // the basic item attributes
        $testitem->number = $this->currentnumber;
        $testitem->correctanswer = $this->itemrecord->correctanswer;
        $testitem->id = $this->itemrecord->id;
        $testitem->type = $this->itemrecord->type;
        $testitem->boxedlayout = $this->uses_boxed_layout();
        $testitem->name = $this->itemrecord->name;
        $testitem->timelimit = $this->itemrecord->timelimit;
        if ($this->forcetitles) {
            $testitem->title = $this->itemrecord->name;
        }
        $testitem->uniqueid = $this->itemrecord->type . $testitem->number;

        // Question instructions
        if (!empty($itemrecord->{constants::TEXTINSTRUCTIONS})) {
            $testitem->iteminstructions = $itemrecord->{constants::TEXTINSTRUCTIONS};
        }

        // Question Text
        $itemtext = file_rewrite_pluginfile_urls(
            $itemrecord->{constants::TEXTQUESTION},
            'pluginfile.php',
            $this->context->id,
            constants::M_COMPONENT,
            constants::TEXTQUESTION_FILEAREA,
            $testitem->id
        );
        $itemtext = format_text($itemtext, FORMAT_MOODLE, $editoroptions);
        if (!empty($itemtext)) {
            $testitem->itemtext = $itemtext;
        }

        // Question media embed
        if (!empty($itemrecord->{constants::MEDIAIFRAME}) && !empty(trim($itemrecord->{constants::MEDIAIFRAME}))) {
            $testitem->itemiframe = $itemrecord->{constants::MEDIAIFRAME};
        }

        // Question media items (upload)
        $mediaurls = $this->fetch_media_urls(constants::MEDIAQUESTION, $itemrecord);
        if ($mediaurls && count($mediaurls) > 0) {
            foreach ($mediaurls as $mediaurl) {
                $fileparts = pathinfo(strtolower($mediaurl));
                switch ($fileparts['extension']) {
                    case "jpg":
                    case "jpeg":
                    case "png":
                    case "gif":
                    case "bmp":
                    case "svg":
                        $testitem->itemimage = $mediaurl;
                        break;

                    case "mp4":
                    case "mov":
                    case "webm":
                    case "ogv":
                        $testitem->itemvideo = $mediaurl;
                        break;

                    case "m4a":
                    case "mp3":
                    case "ogg":
                    case "wav":
                        $testitem->itemaudio = $mediaurl;
                        break;

                    default:
                        // do nothing
                } //end of extension switch
            } //end of for each
        } //end of if mediaurls

        // TTS Question
        if (!empty($itemrecord->{constants::TTSQUESTION}) && !empty(trim($itemrecord->{constants::TTSQUESTION}))) {
            $testitem->itemttsaudio = $itemrecord->{constants::TTSQUESTION};
            $testitem->itemttsaudiovoice = $itemrecord->{constants::TTSQUESTIONVOICE};
            $testitem->itemttsoption = $itemrecord->{constants::TTSQUESTIONOPTION};
            $testitem->itemttsautoplay = $itemrecord->{constants::TTSAUTOPLAY};
            // Fetch Audio URL.
            $testitem->itemttsaudiourl = utils::fetch_polly_url(
                    $this->token,
                    $this->region,
                    $testitem->itemttsaudio,
                    $testitem->itemttsoption,
                    $testitem->itemttsaudiovoice,
                    $this->moduleinstance->id
                );
        }

        // YT Clip
        if (!empty($itemrecord->{constants::YTVIDEOID}) && !empty(trim($itemrecord->{constants::YTVIDEOID}))) {
            $ytvideoid = utils::super_trim($itemrecord->{constants::YTVIDEOID});
            // if its a YT URL we want to parse the id from it
            if (\core_text::strlen($ytvideoid) > 11) {
                $urlbits = [];
                preg_match('/^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/', $ytvideoid, $urlbits);
                if ($urlbits && count($urlbits) > 7) {
                    $ytvideoid = $urlbits[7];
                }
            }
                        $testitem->itemytvideoid = $ytvideoid;
            $testitem->itemytvideostart = $itemrecord->{constants::YTVIDEOSTART};
            $testitem->itemytvideoend = $itemrecord->{constants::YTVIDEOEND};
        }
        // TTS Dialog
        if (!empty($itemrecord->{constants::TTSDIALOG}) && !empty(trim($itemrecord->{constants::TTSDIALOG}))) {
            $itemrecord = utils::unpack_ttsdialogopts($itemrecord);
            $testitem->itemttsdialog = true;
            $testitem->itemttsdialogvisible = $itemrecord->{constants::TTSDIALOGVISIBLE};
            $dialoglines = explode(PHP_EOL, $itemrecord->{constants::TTSDIALOG});
            $linesdata = [];
            foreach ($dialoglines as $theline) {
                if (\core_text::strlen($theline) > 1) {
                    $startchars = \core_text::substr($theline, 0, 2);
                    switch ($startchars) {
                        case 'A)':
                            $speaker = 'a';
                            $voice = $itemrecord->{constants::TTSDIALOGVOICEA};
                            $thetext = \core_text::substr($theline, 2);
                            break;
                        case 'B)':
                            $speaker = 'b';
                            $voice = $itemrecord->{constants::TTSDIALOGVOICEB};
                            $thetext = \core_text::substr($theline, 2);
                            break;
                        case 'C)':
                            $speaker = 'c';
                            $voice = $itemrecord->{constants::TTSDIALOGVOICEC};
                            $thetext = \core_text::substr($theline, 2);
                            break;
                        case '>>':
                            $speaker = "soundeffect";
                            $voice = "soundeffect";
                            $thetext = \core_text::substr($theline, 2);
                            break;
                        default:
                            // if it's just a new line for the previous voice
                            if (count($linesdata) > 0) {
                                $voice = $linesdata[count($linesdata) - 1]->voice;
                                $speaker = $linesdata[count($linesdata) - 1]->speaker;
                                // if they never entered A) B) or C)
                            } else {
                                $voice = $itemrecord->{constants::TTSDIALOGVOICEA};
                                $speaker = "a";
                            }
                            $thetext = $theline;
                    }
                    if (empty(trim($thetext))) {
                        continue;
                    }
                    $lineset = new \stdClass();
                    $lineset->index = count($linesdata);
                    $lineset->speaker = $speaker;
                    $lineset->issoundeffect = ($speaker === "soundeffect");
                    // Speaker blocks are left aligned; B) is indented, C) is indented half as much.
                    // Sound effects are a small centered marker. Labels come from the speakera/b/c
                    // language strings.
                    switch ($speaker) {
                        case 'b':
                            $lineset->indentclass = 'ttsdialog_indent_b';
                            $lineset->label = self::ttsdialog_label($itemrecord, constants::TTSDIALOGLABELB, 'speakerb');
                            break;
                        case 'c':
                            $lineset->indentclass = 'ttsdialog_indent_c';
                            $lineset->label = self::ttsdialog_label($itemrecord, constants::TTSDIALOGLABELC, 'speakerc');
                            break;
                        case 'soundeffect':
                            $lineset->indentclass = '';
                            $lineset->label = '';
                            break;
                        case 'a':
                        default:
                            $lineset->indentclass = '';
                            $lineset->label = self::ttsdialog_label($itemrecord, constants::TTSDIALOGLABELA, 'speakera');
                            break;
                    }
                    $lineset->speakertext = $thetext;
                    $lineset->voice = $voice;
                    $voiceoptions = constants::TTS_NORMAL;
                    if ($lineset->voice == "soundeffect") {
                        $lineset->audiourl = $CFG->wwwroot . '/' . constants::M_PATH . '/sounds/' . utils::super_trim($thetext) . '.mp3';
                    } else {
                        $lineset->audiourl = utils::fetch_polly_url($this->token, $this->region, $thetext, $voiceoptions, $voice, $this->moduleinstance->id);
                    }
                    $linesdata[] = $lineset;
                }
            }
            $testitem->ttsdialoglines = $linesdata;

            // Translation support (mirrors the fiction item type). The translate icon
            // per line lets a learner translate a line into their native language.
            $ttsdialogsourcelang = $this->moduleinstance->ttslanguage;
            $ttsdialognativelang = $this->moduleinstance->nativelang;
            if (get_config(constants::M_COMPONENT, 'setnativelanguage')) {
                $userprefnativelanguage = get_user_preferences(constants::NATIVELANG_PREF);
                if (!empty($userprefnativelanguage)) {
                    $ttsdialognativelang = $userprefnativelanguage;
                }
            }
            $testitem->ttsdialogcmid = $this->context->instanceid;
            $testitem->ttsdialogitemid = $this->itemrecord->id;
            $testitem->ttsdialogsourcelang = $ttsdialogsourcelang;
            $testitem->ttsdialognativelang = $ttsdialognativelang;
            // Only offer translation when a native language is set and it differs from the target language.
            $basesource = strtolower(explode('-', (string) $ttsdialogsourcelang)[0]);
            $basedest = strtolower(explode('-', (string) $ttsdialognativelang)[0]);
            $testitem->ttsdialogcantranslate = !empty($ttsdialognativelang) && $basesource !== $basedest;
            // Translation panel direction follows the translation (native) language, which is
            // independent of the dialog's target language direction (the {{rtl}} class).
            $testitem->ttsdialognativelangrtl = utils::is_rtl($ttsdialognativelang) ? constants::M_CLASS . '_rtl' : '';
        } // end of tts dialog

        // Native Language Chooser.
        if (!empty($itemrecord->{constants::NATIVELANGCHOOSER})) {
            $testitem->{constants::NATIVELANGCHOOSER} = true;
            $testitem->activitynativelang = $this->moduleinstance->nativelang;
            $userprefnativelanguage = get_user_preferences(constants::NATIVELANG_PREF);
            if (empty($userprefnativelanguage)) {
                $usenativelanguage = $testitem->activitynativelang;
            } else {
                $usenativelanguage = $userprefnativelanguage;
            }
            $langoptions = [0 => '--'] + utils::get_lang_options();
            $nativelanglist = [];
            foreach ($langoptions as $value => $label) {
                $selected = ($value == $usenativelanguage);
                $nativelanglist[] = (object) ['value' => $value, 'label' => $label, 'selected' => $selected];
            }
            $testitem->nativelanglist = $nativelanglist;

        } else {
            $testitem->{constants::NATIVELANGCHOOSER} = false;
            $testitem->nativelanglist = [];
        }

        // TTS Passage
        if (!empty($itemrecord->{constants::TTSPASSAGE}) && !empty(trim($itemrecord->{constants::TTSPASSAGE}))) {
            $itemrecord = utils::unpack_ttspassageopts($itemrecord);
            $testitem->itemttspassage = true;
            $withlinebreaks = true;
            $textlines = utils::split_into_sentences($itemrecord->{constants::TTSPASSAGE}, $withlinebreaks);
            $voice = $itemrecord->{constants::TTSPASSAGEVOICE};
            $voiceoptions = $itemrecord->{constants::TTSPASSAGESPEED};
            $linedatas = [];
            foreach ($textlines as $theline) {
                if (!empty(utils::super_trim($theline))) {
                    $linedata = new \stdClass();
                    $linedata->linebreak = false;
                    $linedata->sentence = $theline;
                    $linedata->audiourl = utils::fetch_polly_url($this->token, $this->region, $theline, $voiceoptions, $voice, $this->moduleinstance->id);
                    $linedatas[] = $linedata;
                } else {
                    // If it is not a sentence it is a line break. We add an empty line with no audio.
                    // In mustache we will and line break
                    $linedata = new \stdClass();
                    $linedata->linebreak = true;
                    $linedata->sentence = "";
                    $linedata->audiourl = false;
                    $linedatas[] = $linedata;
                }
            }
            $testitem->ttspassagelines = $linedatas;
        } // end of tts passage

        // Audio story files media items (upload)
        $testitem->audiostory = false;
        $testitem->audiostoryaudio = false;
        $testitem->audiostorysubtitles = false;
        $testitem->audiostoryimages = [];
        $testitem->audiostorymeta = [];
        if (!empty($itemrecord->{constants::AUDIOSTORYMETA})) {
            $audiostorymeta = explode(PHP_EOL, $itemrecord->{constants::AUDIOSTORYMETA});
            foreach ($audiostorymeta as $timestamp) {
                $seconds = $this->time_to_seconds($timestamp);
                if ($seconds !== false) {
                    $testitem->audiostorymeta[] = $seconds;
                }
            }
        }
        $mediaurls = $this->fetch_media_urls(constants::AUDIOSTORY, $itemrecord);
        if ($mediaurls && count($mediaurls) > 0) {
            foreach ($mediaurls as $mediaurl) {
                $fileparts = pathinfo(strtolower($mediaurl));
                switch ($fileparts['extension']) {
                    case "jpg":
                    case "jpeg":
                    case "png":
                    case "gif":
                    case "bmp":
                    case "svg":
                        $imagecount = count($testitem->audiostoryimages);
                        $entrytime = isset($testitem->audiostorymeta[$imagecount]) ? $testitem->audiostorymeta[$imagecount] : '';
                        $testitem->audiostoryimages[] = ['mediaurl' => $mediaurl, 'entrytime' => $entrytime];
                        break;

                    case "m4a":
                    case "mp3":
                    case "ogg":
                    case "wav":
                        $testitem->audiostoryaudio = $mediaurl;
                        break;
                    case "vtt":
                        $testitem->audiostorysubtitles = $mediaurl;
                        break;

                    default:
                        // do nothing
                } //end of extension switch
            } //end of for each

            // If we do not have an audio file, we will not have an audio story.
            // Its hacky but lets allow users to use the TTS audio file as this.
            if (empty($testitem->audiostoryaudio) && !empty($itemrecord->{constants::TTSQUESTION}) && !empty(trim($itemrecord->{constants::TTSQUESTION}))) {
                $testitem->audiostoryaudio = utils::fetch_polly_url(
                    $this->token,
                    $this->region,
                    $testitem->itemttsaudio,
                    $testitem->itemttsoption,
                    $testitem->itemttsaudiovoice,
                    $this->moduleinstance->id
                );
                // Unset the TTS audio as we are using the audio story audio.
                unset($testitem->itemttsaudio);
            }

            // Set the Zoom and Pan
            $testitem->audiostoryzoomandpan = $itemrecord->{constants::AUDIOSTORYZOOMANDPAN};

            // If we have enough data to make an audio story, enable it
            if (
                count($testitem->audiostoryimages) > 0
                && !empty($testitem->audiostoryaudio)
                && count($testitem->audiostorymeta) > 0
            ) {
                $testitem->audiostory = true;
            }
        } //end of if audio story

        // Question TextArea.
        if (!empty($itemrecord->{constants::QUESTIONTEXTAREA}) && !empty(trim($itemrecord->{constants::QUESTIONTEXTAREA}))) {
            $itemtextarea = $itemrecord->{constants::QUESTIONTEXTAREA};
            // Editor content is stored as HTML, but imported/legacy content may be plain
            // text whose line breaks would otherwise be lost.
            if (
                stripos($itemtextarea, '<p') === false
                && stripos($itemtextarea, '<br') === false
                && stripos($itemtextarea, '<div') === false
            ) {
                $itemtextarea = nl2br($itemtextarea);
            }
            $testitem->itemtextarea = format_text($itemtextarea, FORMAT_HTML, $editoroptions);
        }

        // Show text prompt or dots, for listen and repeat really.
        $testitem->show_text = $itemrecord->{constants::SHOWTEXTPROMPT};

        // For right to left languages we want to add the RTL direction and right justify.
        if (utils::is_rtl($this->moduleinstance->ttslanguage)) {
            $testitem->rtl = constants::M_CLASS . '_rtl';
        } else {
            $testitem->rtl = '';
        }

        return $testitem;
    }

    /**
     * Resolve a TTS dialog speaker label: the author's custom label if set, otherwise
     * the corresponding speakera/b/c language string.
     *
     * @param \stdClass $itemrecord The item record (already unpacked via unpack_ttsdialogopts).
     * @param string $labelcolumn The TTSDIALOGLABEL* opts key.
     * @param string $defaultstringkey The fallback language string key (speakera/b/c).
     * @return string
     */
    protected static function ttsdialog_label($itemrecord, $labelcolumn, $defaultstringkey) {
        $label = isset($itemrecord->{$labelcolumn}) ? trim($itemrecord->{$labelcolumn}) : '';
        if ($label !== '') {
            return $label;
        }
        return get_string($defaultstringkey, constants::M_COMPONENT);
    }

    protected function get_text_answer_elements($testitem) {
        $itemrecord = $this->itemrecord;
        // Text answer fields
        for ($anumber = 1; $anumber <= constants::MAXANSWERS; $anumber++) {
            if (!empty($itemrecord->{constants::TEXTANSWER . $anumber}) && !empty(trim($itemrecord->{constants::TEXTANSWER . $anumber}))) {
                $testitem->{'customtext' . $anumber} = $itemrecord->{constants::TEXTANSWER . $anumber};
            }
        }
        return $testitem;
    }

    protected function get_polly_options($testitem) {

        // if we need polly then lets do that
        $testitem->usevoice = $this->itemrecord->{constants::POLLYVOICE};
        $testitem->voiceoption = $this->itemrecord->{constants::POLLYOPTION};
        return $testitem;
    }

    protected function set_layout($testitem) {

        // vertical layout or horizontal layout determined by content options
        $textset = isset($testitem->itemtextarea) && !empty($testitem->itemtextarea);
        $imageset = isset($testitem->itemimage) && !empty($testitem->itemimage);
        $videoset = isset($testitem->itemvideo) && !empty($testitem->itemvideo);
        $iframeset = isset($testitem->itemiframe) && !empty($testitem->itemiframe);
        $ytclipset = isset($testitem->itemytvideoid) && !empty($testitem->itemytvideoid);

        // layout
        $testitem->layout = $this->itemrecord->{constants::LAYOUT};
        if ($testitem->layout == constants::LAYOUT_AUTO) {
            // unless the item type prefers to stay stacked, any big content item makes it horizontal layout
            if (!$this->autolayout_prefers_vertical()) {
                if ($textset || $imageset || $videoset || $iframeset || $ytclipset) {
                    $testitem->horizontal = true;
                }
            }
        } else {
            switch ($testitem->layout) {
                case constants::LAYOUT_HORIZONTAL:
                    $testitem->horizontal = true;
                    break;
                case constants::LAYOUT_VERTICAL:
                    $testitem->vertical = true;
                    break;
                case constants::LAYOUT_MAGAZINE:
                    $testitem->magazine = true;
                    break;
            }
        }
        return $testitem;
    }

    /**
     * Fetches the media urls for the item.
     *
     * @param string $mediatype either "audio" or "video"
     * @param int $sentencefieldindex The customtext field that holds all the sentences usually: 1 (ie customtext1)
     * @return array
     */
    protected function fetch_sentence_media($mediatype, $sentencefieldindex) {
        global $DB;
        $mediaurls = [];
        // File area to fetch from, eg "customfile2_image" or "fileanswer1_audio".
        $filearea = constants::FILEANSWER . $sentencefieldindex . '_' . $mediatype;
        if (isset($this->itemrecord->id) && !empty($this->itemrecord->id)) {
            $itemid = $this->itemrecord->id;
            $fs = get_file_storage();
            $files = $fs->get_area_files($this->context->id, constants::M_COMPONENT, $filearea, $itemid, 'id', false);
            foreach ($files as $file) {
                if ($file->is_directory()) {
                    continue;
                }
                $filename = $file->get_filename();
                $filepath = $file->get_filepath();
                $filenamenoextension = pathinfo($file->get_filename(), PATHINFO_FILENAME);
                $mediaurl = \moodle_url::make_pluginfile_url(
                    $this->context->id,
                    constants::M_COMPONENT,
                    $filearea,
                    $itemid,
                    $filepath,
                    $filename
                );
                $mediaurls[$filenamenoextension] = $mediaurl->__toString();
            }
        } //end if item id
        return $mediaurls;
    }

    /*
     * Processes gap fill sentences : TO DO not implemented, implement this
     */
    protected function process_gapfill_sentences($sentences) {
        $sentenceobjects = [];
        foreach ($sentences as $sentence) {
            $sentence = utils::super_trim($sentence);
            if (empty($sentence)) {
                continue;
            }

            // TO DO replace [x] with gaps
            $prompt = $sentence;

            $s = new \stdClass();
            $s->index = $index;
            $s->indexplusone = $index + 1;
            $s->sentence = $sentence;
            $s->prompt = $prompt;
            $s->displayprompt = $prompt;
            $s->length = \core_text::strlen($s->sentence);

            $index++;
            $sentenceobjects[] = $s;
        }
        return $sentenceobjects;
    }

    /*
     * Processes gap fill sentences : TO DO not implemented, implement this
     */
    protected function process_typinggapfill_sentences($sentences) {
        return $this->parse_gapfill_sentences($sentences);
    }

    /*
     * Processes listening gap fill sentences : TO DO not implemented, implement this
     */
    protected function process_listeninggapfill_sentences($sentences) {
        $thesentences = $this->parse_gapfill_sentences($sentences);
        $customsentenceaudio = $this->fetch_sentence_media('audio', 1);
        foreach ($thesentences as $sentence) {
            if (isset($customsentenceaudio[$sentence->indexplusone])) {
                $sentence->audiourl = $customsentenceaudio[$sentence->indexplusone];
            } else {
                // If we have no custom audio then we use the polly audio.
                $sentence->audiourl = utils::fetch_polly_url(
                    $this->token,
                    $this->region,
                    $sentence->prompt,
                    $this->itemrecord->{constants::POLLYOPTION},
                    $this->itemrecord->{constants::POLLYVOICE},
                    $this->moduleinstance->id
                );
            }
        }
        return $thesentences;
    }

    /*
     * Processes speaking gap fill sentences
     */
    protected function process_speakinggapfill_sentences($sentences) {

        $thesentences = $this->parse_gapfill_sentences($sentences);
        $phoneticstring = $this->itemrecord->phonetic;
        $phonetics = false;
        if (!empty($phoneticstring)) {
            $phonetics = explode(PHP_EOL, $phoneticstring);
        }
        $customsentenceaudio = $this->fetch_sentence_media('audio', 1);
        foreach ($thesentences as $i => $sentence) {
            // Get audio url
            if (isset($customsentenceaudio[$sentence->indexplusone])) {
                $sentence->audiourl = $customsentenceaudio[$sentence->indexplusone];
            } else {
                // If we have no custom audio then we use the polly audio.
                $sentence->audiourl = utils::fetch_polly_url(
                    $this->token,
                    $this->region,
                    $sentence->prompt,
                    $this->itemrecord->{constants::POLLYOPTION},
                    $this->itemrecord->{constants::POLLYVOICE},
                    $this->moduleinstance->id
                );
            }

            // get phonetics for each sentence
            if ($phonetics && array_key_exists($i, $phonetics)) {
                $ps = utils::super_trim($phonetics[$i]);
                $psarray = explode('|#', $ps);
                // Phonetics needs to be stored at the word level as are sentence->words
                // currently it is just the entire phonetics string is sent up
                // so the transcript vs passage match will always match phonetically partially
                // ie words match like this: [extraction] <--> I am doing an explanation
                // but phonetics match like this: ai im doiz za extactshin ---> ai im doiz za explanashin
                // ie there will always be a match and its meaningless
                // So until we can reliably do this (multi word gaps etc) we are turning it off
                // $sentence->phonetic = array_key_exists(0, $psarray) ? utils::super_trim($psarray[0]) : '';
                $sentence->phonetic = "";

                $sentence->segmentedsentence = array_key_exists(1, $psarray) ? utils::super_trim($psarray[1]) : '';
                if (empty($sentence->segmentedsentence)) {
                    list($phones, $segmentedsentence) = utils::fetch_phones_and_segments(
                        $sentence->sentence,
                        $this->moduleinstance->ttslanguage,
                        $this->moduleinstance->region
                    );
                    $sentence->segmentedsentence = $segmentedsentence;
                }
            }
        }

        return $thesentences;
    }

    /*
     * Processes listening gap fill sentences
     */
    public function parse_gapfill_sentences($sentences, $allowmultiwordgaps = false) {

        $sentenceobjects = [];
        $sentenceimages = $this->fetch_sentence_media('image', 1);
        $sentenceindex = -1;
        foreach ($sentences as $sentence) {
            $sentenceindex++;
            $arr = explode('|', utils::super_trim($sentence));
            $sentence = utils::super_trim($arr[0] ?? '');
            $definition = utils::super_trim($arr[1] ?? '');
            $extra = utils::super_trim($arr[2] ?? '');
            if (empty($sentence)) {
                continue;
            }

            $parsedstring = [];
            $started = false;
            // Arrays to hold the masked words, gap words, and extra words.
            // Masked words gap words (isgap = true) that include the gap markers [].
            // We use this for char level entry, partic. masking partial gap words like en[courage]ment
            $maskedwords = [];
            // Gap words array holds the words flagging them as gaps or non-gaps and other metadata.
            $gapwords = [];
            // Extra words are distractors probably just for wordshuffle
            $extrawords = [];
            if (utils::super_trim($extra) !== '') {
                // If extra contains a comma, split on commas, otherwise split on spaces.
                $extrawordsseperator = (strpos($extra, ',') !== false) ? ',' : ' ';
                $extrawords = explode($extrawordsseperator, $extra);
            }

            // Split tokens while preserving bracketed gaps. We are not separating gapwords yet
            // This will turn "The [quick brown] fox" into ["The", "[quick brown]", "fox"].
            // NB it will separate the part after ] as a separate word. [fath]er => ["[fath]", "er"]
            // if that is a problem, its probably better to fix the sentence.
            // if (preg_match_all('/\[[^\]]+\]|[^\s]+/', $sentence, $matches)) {
            // We use a negated character class [^\s\.\,\!\?\;\:\)\}\"\]]* instead of [^\s]*
            // so that standard punctuation (like ?) is NOT glued to the gap, but alphanumeric
            // suffixes (like in [fath]er) ARE glued.
            if (preg_match_all('/\[[^\]]+\][^\s\.\,\!\?\;\:\)\}\"\]]*|[^\s]+/', $sentence, $matches)) {
                $words = $matches[0];
            } else {
                $words = [];
            }

            // If allowmultiwordgaps is false, we need to further split any gaps that contain spaces into multiple gaps.
            // This will turn ["The", "[quick brown]", "fox"] into ["The", "[quick]", "[brown]", "fox"].
            if (!$allowmultiwordgaps) {
                $expandedwords = [];
                foreach ($words as $word) {
                    if (preg_match('/^\[.*\]$/', $word)) {
                        $cleanedword = str_replace(['[', ']'], '', $word);
                        $subwords = explode(' ', $cleanedword);
                        foreach ($subwords as $subword) {
                            $expandedwords[] = '[' . $subword . ']';
                        }
                    } else {
                        $expandedwords[] = $word;
                    }
                }
                $words = $expandedwords;
            }

            // Remove the brackets from gap words and build maskedwords and gapwords arrays.
            // $gapindex is used to index gap words (isgap = true) only.
            $gapindex = 0;
            foreach ($words as $index => $word) {
                // Check if the word is a gap (enclosed in square brackets).
                if (preg_match('/^\[.*\]$/', $word)) {
                    $cleanedword = str_replace(['[', ']'], '', $word);
                    $maskedwords[$index] = $word;
                    $gapwords[] = [
                        'index' => $gapindex,
                        'isgap' => true,
                        'word' => $cleanedword,
                    ];
                    $gapindex++;
                } else {
                    // Check if the word contains bracketed portions (e.g., "pre[fix]post").
                    if (preg_match('/\[[^\]]+\]/', $word, $m)) {
                        // This will capture the bracketed portion. eg d[oubl]e => [oubl].
                        $truncatedword = $m[0];
                        $cleanedword = str_replace(['[', ']'], '', $truncatedword);
                        $maskedwords[$index] = $word;
                        $gapwords[] = [
                            'index' => $gapindex,
                            'isgap' => true,
                            'word' => $cleanedword,
                        ];
                        $gapindex++;
                    } else {
                        // Otherwise, it's a normal word and not a gap.
                        $gapwords[] = [
                            'isgap' => false,
                            'word' => $word,
                        ];
                    }
                }
            }

            // Now we build the parsedstring array at character level.
            $enc = mb_detect_encoding($sentence);
            foreach ($gapwords as $gindex => $gapword) {
                // Get the word and add a trailing space to separate words.
                $ismasked = $gapword['isgap'] && array_key_exists($gindex, $maskedwords);
                if ($ismasked) {
                    $theword = $maskedwords[$gindex] . ' ';
                } else {
                    $theword = $gapword['word'] . ' ';
                }
                $characters = utils::do_mb_str_split($theword, 1, $enc);
                // Encoding parameter is required for < PHP 8.0.
                // $characters=str_split($sentence); //DEBUG ONLY - fails on multibyte characters.
                // $characters=mb_str_split($sentence); //DEBUG ONLY - - only exists on 7.4 and greater .. ie NOT for 7.3.

                foreach ($characters as $character) {
                    if ($character === '[') {
                        $started = true;
                        continue;
                    }
                    if ($character === ']') {
                        $started = false;
                        continue;
                    }
                    if (
                        $ismasked &&
                        $character !== " " && $character !== "." && $character !== "," &&
                        $character !== "!" && $character !== "?" && $character !== ";" && $character !== ":"
                    ) {
                        if ($started) {
                            $parsedstring[] = ['index' => $gindex, 'character' => $character, 'type' => 'input'];
                        } else {
                            $parsedstring[] = ['index' => $gindex, 'character' => $character, 'type' => 'mtext'];
                        }
                    } else {
                        $parsedstring[] = ['index' => $gindex, 'character' => $character, 'type' => 'text'];
                    }
                }
            }

            $sentence = str_replace(['[', ']', ',', '.'], ['', '', '', ''], $sentence);
            $prompt = $sentence;

            $s = new \stdClass();
            $s->index = $sentenceindex;
            $s->indexplusone = $sentenceindex + 1;
            $s->sentence = $sentence;
            $s->prompt = $prompt;
            $s->definition = $definition;
            $s->displayprompt = $prompt;
            $s->length = \core_text::strlen($s->sentence);
            $s->parsedstring = $parsedstring;
            $s->imageurl = isset($sentenceimages[$s->indexplusone]) ? $sentenceimages[$s->indexplusone] : false;
            $s->words = $maskedwords;
            $s->gapwords = $gapwords;
            $s->extrawords = $extrawords;

            $sentenceobjects[] = $s;
        }

        return $sentenceobjects;
    }

    /*
     * Takes an array of sentences and phonetics for the same, and returns sentence objects with display and spoken and phonetic data
     *
     */
    protected function process_spoken_sentences($sentences, $phonetics, $dottify = false, $isssml = false) {
        // build a sentences object for mustache and JS
        $index = 0;
        $sentenceobjects = [];

        // Prepare sentence media.
        $sentenceimages = $this->fetch_sentence_media('image', 1);
        $sentenceaudio = $this->fetch_sentence_media('audio', 1);

        $sentenceindex = 0;
        foreach ($sentences as $sentence) {
            $sentence = utils::super_trim($sentence);
            if (empty($sentence)) {
                continue;
            }
            // Sentence index starts at 1 and keys with sentenceaudios and sentenceimages
            $sentenceindex++;

            // build prompt and displayprompt and sentence which could be different
            // prompt = audio_prompt
            // sentence = target_sentence ie what the student should say (correct response)
            // displayprompt = the text_prompt that we show the student before they speak
            $hintdisplay = false;

            // dottify = if we dont show the text and just show dots ..
            if ($dottify) {
                $prompt = $this->dottify_text($sentence);
                $displayprompt = $prompt;
            } else {
                // if we have a pipe prompt = array[0] and response = array[1]
                $sentencebits = explode('|', $sentence);
                if (count($sentencebits) > 1) {
                    $hintdisplay = true;
                    $prompt = utils::super_trim($sentencebits[0]);
                    $sentence = utils::super_trim($sentencebits[1]);
                    if (count($sentencebits) > 2) {
                        $displayprompt = utils::super_trim($sentencebits[2]);
                    } else {
                        $displayprompt = $prompt;
                    }
                } else {
                    $prompt = $sentence;
                    $displayprompt = $sentence;
                }
            }

            // we strip the HTML tags off if it is SSML
            // probably no harm in doing this if its SSML or not ...
            if ($isssml) {
                $displayprompt = strip_tags($displayprompt);
                $sentence = strip_tags($sentence);
            }

            if ($this->language == constants::M_LANG_JAJP) {
                $thephonetics = false;
                if (isset($phonetics[$index]) && !empty($phonetics[$index])) {
                    $thephonetics = utils::super_trim($phonetics[$index]);
                }
                $sentence = $this->process_japanese_phonetics($sentence, $thephonetics);
            }

            // We prepare the audio url.
            if (isset($sentenceaudio[$sentenceindex])) {
                $theaudiourl = $sentenceaudio[$sentenceindex];
            } else {
                // If we have no custom audio then we use the polly audio.
                $theaudiourl = utils::fetch_polly_url(
                    $this->token,
                    $this->region,
                    $prompt,
                    $this->itemrecord->{constants::POLLYOPTION},
                    $this->itemrecord->{constants::POLLYVOICE},
                    $this->moduleinstance->id
                );
            }

            // Build the sentence object.
            $s = new \stdClass();
            $s->index = $index;
            $s->indexplusone = $index + 1;
            $s->sentence = $sentence;
            $s->prompt = $prompt;
            $s->displayprompt = $displayprompt;
            $s->length = \core_text::strlen($s->sentence);
            $s->imageurl = isset($sentenceimages[$sentenceindex]) ? $sentenceimages[$sentenceindex] : false;
            $s->audiourl = $theaudiourl;
            $s->hintdisplay = $hintdisplay;

            // Add phonetics if we have them.
            if (isset($phonetics[$index]) && !empty($phonetics[$index])) {
                $ps = utils::super_trim($phonetics[$index]);
                $psarray = explode('|', $ps);
                $s->phonetic = array_key_exists(0, $psarray) ? utils::super_trim($psarray[0]) : '';
            } else {
                $s->phonetic = '';
            }

            $index++;
            $sentenceobjects[] = $s;
        }
        return $sentenceobjects;
    }

    // by default we do nothing, but for japanese listen_and_speak, dictation chat and shortanswer, this is overrridden
    protected function process_japanese_phonetics($sentence, $thephonetics = false) {
        return $sentence;
    }

    protected function set_cloudpoodll_details($testitem, $maxtime = 15) {
        global $USER, $CFG;

        $itemrecord = $this->itemrecord;
        $testitem->region = $this->region;
        $testitem->cloudpoodlltoken = $this->token;
        $testitem->wwwroot = $CFG->wwwroot;
        $testitem->language = $this->language;
        $testitem->hints = '';
        $testitem->appid = constants::M_COMPONENT;
        $testitem->owner = hash('md5', $USER->username);
        $testitem->usevoice = $itemrecord->{constants::POLLYVOICE};
        $testitem->voiceoption = $itemrecord->{constants::POLLYOPTION};
        $testitem->cloudpoodllurl = utils::get_cloud_poodll_server();

        // TT Recorder stuff
        $testitem->waveheight = 75;
        // passagehash for several reasons could rightly be empty
        // if its full it will be region|hash eg tokyo|2353531453415134545
        // we just want the hash here
        $testitem->passagehash = "";
        if (!empty($itemrecord->passagehash)) {
            $hashbits = explode('|', $itemrecord->passagehash, 2);
            if (count($hashbits) == 2) {
                $testitem->passagehash = $hashbits[1];
            }
        }

        // transcription server url
        $testitem->asrurl = utils::fetch_lang_server_url($this->region, 'transcribe');

        // recording max time
        $testitem->maxtime = $maxtime;

        return $testitem;
    }

    protected function dottify_text($rawtext) {
        $re = '/[^\'!"#$%&\\\\\'()\*+,\-\.\/:;<=> ?@\[\\\\\]\^_`{|}~\']/u';
        $subst = '•';

        $dots = preg_replace($re, $subst, $rawtext);
        return $dots;
    }

    protected function fetch_media_urls($filearea, $item) {
        // get question audio div (not so easy)
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, constants::M_COMPONENT, $filearea, $item->id);
        $urls = [];
        foreach ($files as $file) {
            $filename = $file->get_filename();
            if ($filename == '.') {
                continue;
            }
            $filepath = '/';
            $mediaurl = \moodle_url::make_pluginfile_url(
                $this->context->id,
                constants::M_COMPONENT,
                $filearea,
                $item->id,
                $filepath,
                $filename
            );
            $urls[] = $mediaurl->__toString();
        }
        return $urls;
    }

    public function update_insert_item() {
        global $DB, $USER;

        $ret = new \stdClass();
        $ret->error = false;
        $ret->message = '';
        $ret->payload = null;
        $data = $this->itemrecord;

        $theitem = new \stdClass();
        $theitem->minilesson = $this->moduleinstance->id;
        if (!isset($theitem->id) && isset($data->itemid)) {
            $theitem->id = $data->itemid;
        }
        $theitem->visible = $data->visible;
        $theitem->itemorder = $data->itemorder;
        $theitem->type = $data->type;
        $theitem->name = $data->name;
        $theitem->phonetic = $data->phonetic;
        $theitem->passagehash = $data->passagehash;
        $theitem->modifiedby = $USER->id;
        $theitem->timemodified = time();

        // first insert a new item if we need to
        // that will give us a itemid, we need that for saving files
        if (empty($data->itemid)) {
            $theitem->{constants::TEXTQUESTION} = '';
            $theitem->timecreated = time();
            $theitem->createdby = $USER->id;

            // get itemorder
            $theitem->itemorder = self::fetch_next_item_order($this->moduleinstance->id);

            // create a rsquestionkey
            $theitem->rsquestionkey = self::create_itemkey();

            // try to insert it
            if (!$theitem->id = $DB->insert_record(constants::M_QTABLE, $theitem)) {
                $ret->error = true;
                $ret->message = "Could not insert minilesson item!";
                return $ret;
            }
        } //end of if empty($data->itemid)

        // handle all the text questions
        // if its an editor field, do this
        if (property_exists($data, constants::TEXTQUESTION . '_editor')) {
            $data = file_postupdate_standard_editor(
                $data,
                constants::TEXTQUESTION,
                $this->editoroptions,
                $this->context,
                constants::M_COMPONENT,
                constants::TEXTQUESTION_FILEAREA,
                $theitem->id
            );
            $theitem->{constants::TEXTQUESTION} = $data->{constants::TEXTQUESTION};
            $theitem->{constants::TEXTQUESTION_FORMAT} = $data->{constants::TEXTQUESTION_FORMAT};
            // if its a text area field, do this
        } else if (property_exists($data, constants::TEXTQUESTION)) {
            $theitem->{constants::TEXTQUESTION} = $data->{constants::TEXTQUESTION};
        }

        // Files (audio or images) for answer options
        for ($i = 1; $i <= constants::MAXANSWERS; $i++) {
            $fileareas = [constants::FILEANSWER . $i, constants::FILEANSWER . $i . '_audio', constants::FILEANSWER . $i . '_image'];
            foreach ($fileareas as $thefilearea) {
                if (property_exists($data, $thefilearea)) {
                    // if this is from an import, it will be an array
                    if (is_array($data->{$thefilearea})) {
                        foreach ($data->{$thefilearea} as $filename => $filecontent) {
                            $filerecord = [
                                'contextid' => $this->context->id,
                                'component' => constants::M_COMPONENT,
                                'filearea' => $thefilearea,
                                'itemid' => $theitem->id,
                                'filepath' => '/',
                                'filename' => $filename,
                                'userid' => $USER->id,
                            ];
                            $fs = get_file_storage();
                            $fs->create_file_from_string($filerecord, base64_decode($filecontent));
                        }
                    } else {
                        // if this is from a form submission, this will involve draft files
                        switch ($thefilearea) {
                            case constants::FILEANSWER . $i:
                                // save multichoice question answer images or audios
                                file_save_draft_area_files(
                                    $data->{$thefilearea},
                                    $this->context->id,
                                    constants::M_COMPONENT,
                                    $thefilearea,
                                    $theitem->id,
                                    $this->filemanageroptions
                                );
                                break;
                            case constants::FILEANSWER . $i . '_audio':
                                // save sentence audio
                                file_save_draft_area_files(
                                    $this->itemrecord->{$thefilearea},
                                    $this->context->id,
                                    constants::M_COMPONENT,
                                    $thefilearea,
                                    $theitem->id,
                                    array_merge(
                                        $this->filemanageroptions,
                                        ['accepted_types' => 'audio', 'maxfiles' => -1]
                                    )
                                );
                                break;
                            case constants::FILEANSWER . $i . '_image':
                                // save sentence image
                                file_save_draft_area_files(
                                    $this->itemrecord->{$thefilearea},
                                    $this->context->id,
                                    constants::M_COMPONENT,
                                    $thefilearea,
                                    $theitem->id,
                                    array_merge(
                                        $this->filemanageroptions,
                                        ['accepted_types' => 'image', 'maxfiles' => -1]
                                    )
                                );
                                break;
                        }
                    }
                } //end of if property exists filearea
            } //end of foreach file areas
        } //end of max answers loop

        // Question instructions
        if (property_exists($data, constants::TEXTINSTRUCTIONS)) {
            $theitem->{constants::TEXTINSTRUCTIONS} = $data->iteminstructions;
        }

        // Time limit.
        if (property_exists($data, constants::TIMELIMIT)) {
            $theitem->{constants::TIMELIMIT} = $data->{constants::TIMELIMIT};
        }

        // layout
        if (property_exists($data, constants::LAYOUT)) {
            $theitem->{constants::LAYOUT} = $data->{constants::LAYOUT};
        } else {
            $theitem->{constants::LAYOUT} = constants::LAYOUT_AUTO;
        }

        // Item media
        if (property_exists($data, constants::MEDIAQUESTION)) {
            // if this is from an import, it will be an array
            if (is_array($data->{constants::MEDIAQUESTION})) {
                foreach ($data->{constants::MEDIAQUESTION} as $filename => $filecontent) {
                    $filerecord = [
                        'contextid' => $this->context->id,
                        'component' => constants::M_COMPONENT,
                        'filearea' => constants::MEDIAQUESTION,
                        'itemid' => $theitem->id,
                        'filepath' => '/',
                        'filename' => $filename,
                        'userid' => $USER->id,
                    ];
                    $fs = get_file_storage();
                    $fs->create_file_from_string($filerecord, base64_decode($filecontent));
                }
            } else {
                // if this is from a form submission, this will involve draft files
                file_save_draft_area_files(
                    $data->{constants::MEDIAQUESTION},
                    $this->context->id,
                    constants::M_COMPONENT,
                    constants::MEDIAQUESTION,
                    $theitem->id,
                    $this->filemanageroptions
                );
            }
        }

        // Item TTS.
        if (property_exists($data, constants::TTSQUESTION)) {
            $theitem->{constants::TTSQUESTION} = $data->{constants::TTSQUESTION};
            if (property_exists($data, constants::TTSQUESTIONVOICE)) {
                $theitem->{constants::TTSQUESTIONVOICE} = $data->{constants::TTSQUESTIONVOICE};
            } else {
                $theitem->{constants::TTSQUESTIONVOICE} = 'Amy';
            }
            if (property_exists($data, constants::TTSQUESTIONOPTION)) {
                $theitem->{constants::TTSQUESTIONOPTION} = $data->{constants::TTSQUESTIONOPTION};
            } else {
                $theitem->{constants::TTSQUESTIONOPTION} = constants::TTS_NORMAL;
            }
            if (property_exists($data, constants::TTSAUTOPLAY)) {
                $theitem->{constants::TTSAUTOPLAY} = $data->{constants::TTSAUTOPLAY};
            } else {
                $theitem->{constants::TTSAUTOPLAY} = 0;
            }
        }

        // Item Text Area.
        $edoptions = constants::ITEMTEXTAREA_EDOPTIONS;
        $edoptions['context'] = $this->context;
        if (property_exists($data, constants::QUESTIONTEXTAREA . '_editor')) {
            $data->{constants::QUESTIONTEXTAREA . 'format'} = FORMAT_HTML;
            $data = file_postupdate_standard_editor(
                $data,
                constants::QUESTIONTEXTAREA,
                $edoptions,
                $this->context,
                constants::M_COMPONENT,
                constants::TEXTQUESTION_FILEAREA,
                $theitem->id
            );
            $theitem->{constants::QUESTIONTEXTAREA} = utils::super_trim($data->{constants::QUESTIONTEXTAREA});
        } else {
            $theitem->{constants::QUESTIONTEXTAREA} = utils::super_trim($data->{constants::QUESTIONTEXTAREA});
        }

        // Item YT Clip.
        if (property_exists($data, constants::YTVIDEOID)) {
            $theitem->{constants::YTVIDEOID} = $data->{constants::YTVIDEOID};
            if (property_exists($data, constants::YTVIDEOSTART)) {
                $theitem->{constants::YTVIDEOSTART} = $data->{constants::YTVIDEOSTART};
            }
            if (property_exists($data, constants::YTVIDEOEND)) {
                $theitem->{constants::YTVIDEOEND} = $data->{constants::YTVIDEOEND};
            }
        }

        // TTS Dialog.
        if (property_exists($data, constants::TTSDIALOG) && $data->{constants::TTSDIALOG} !== null) {
            $theitem->{constants::TTSDIALOG} = $data->{constants::TTSDIALOG};
            $theitem->{constants::TTSDIALOGOPTS} = utils::pack_ttsdialogopts($data);
        }

        // TTS Passage.
        if (property_exists($data, constants::TTSPASSAGE) && $data->{constants::TTSPASSAGE} !== null) {
            $theitem->{constants::TTSPASSAGE} = $data->{constants::TTSPASSAGE};
            $theitem->{constants::TTSPASSAGEOPTS} = utils::pack_ttspassageopts($data);
        }

        // Audio Story.
        if (property_exists($data, constants::AUDIOSTORYMETA) && $data->{constants::AUDIOSTORYMETA} !== null) {
            $theitem->{constants::AUDIOSTORYMETA} = $data->{constants::AUDIOSTORYMETA};
            $theitem->{constants::AUDIOSTORYZOOMANDPAN} = $data->{constants::AUDIOSTORYZOOMANDPAN};
        }
        if (property_exists($data, constants::AUDIOSTORY)) {
            // if this is from an import, it will be an array
            if (is_array($data->{constants::AUDIOSTORY})) {
                foreach ($data->{constants::AUDIOSTORY} as $filename => $filecontent) {
                    $filerecord = [
                        'contextid' => $this->context->id,
                        'component' => constants::M_COMPONENT,
                        'filearea' => constants::AUDIOSTORY,
                        'itemid' => $theitem->id,
                        'filepath' => '/',
                        'filename' => $filename,
                        'userid' => $USER->id,
                    ];
                    $fs = get_file_storage();
                    $fs->create_file_from_string($filerecord, base64_decode($filecontent));
                }
            } else {
                // If this is from a form submission, this will involve draft files.
                $asfilemanageroptions = self::fetch_filemanager_options($this->course, -1);
                $asfilemanageroptions['accepted_types'] = '*';
                file_save_draft_area_files(
                    $data->{constants::AUDIOSTORY},
                    $this->context->id,
                    constants::M_COMPONENT,
                    constants::AUDIOSTORY,
                    $theitem->id,
                    $asfilemanageroptions
                );
            }
        }

        // Save correct answer if we have one.
        if (property_exists($data, constants::CORRECTANSWER)) {
            $theitem->{constants::CORRECTANSWER} = $data->{constants::CORRECTANSWER};
        }

        // Save Native Language Chooser.
        if (property_exists($data, constants::NATIVELANGCHOOSER)) {
            $theitem->{constants::NATIVELANGCHOOSER} = $data->{constants::NATIVELANGCHOOSER};
        }

        // save text answers and other data in custom text
        // could be editor areas
        for ($anumber = 1; $anumber <= constants::MAXCUSTOMTEXT; $anumber++) {
            // if its an editor field, do this
            if (property_exists($data, constants::TEXTANSWER . $anumber . '_editor')) {
                $data = file_postupdate_standard_editor(
                    $data,
                    constants::TEXTANSWER . $anumber,
                    $this->editoroptions,
                    $this->context,
                    constants::M_COMPONENT,
                    constants::TEXTANSWER_FILEAREA . $anumber,
                    $theitem->id
                );
                $theitem->{constants::TEXTANSWER . $anumber} = $data->{'customtext' . $anumber};
                $theitem->{constants::TEXTANSWER . $anumber . 'format'} = $data->{constants::TEXTANSWER . $anumber . 'format'};
                // if its a text field, do this
            } else if (property_exists($data, constants::TEXTANSWER . $anumber)) {
                $thetext = utils::super_trim($data->{constants::TEXTANSWER . $anumber});
                // segment the text if it is japanese and not already segmented
                // TO DO: remove this
                /*
                 if($minilesson->ttslanguage == constants::M_LANG_JAJP &&
                 ($data->type==CONSTANTS::TYPE_LISTENREPEAT ||
                 $data->type==CONSTANTS::TYPE_SPEECHCARDS ||
                 $data->type==CONSTANTS::TYPE_SHORTANSWER )){
                 if(strpos($thetext,' ')==false){
                 //  $thetext = utils::segment_japanese($thetext);
                 }
                 }
                 */
                $theitem->{constants::TEXTANSWER . $anumber} = $thetext;
            }
        }

        // We might have other customdata.
        for ($anumber = 1; $anumber <= constants::MAXCUSTOMDATA; $anumber++) {
            if (property_exists($data, constants::CUSTOMDATA . $anumber)) {
                $theitem->{constants::CUSTOMDATA . $anumber} = $data->{constants::CUSTOMDATA . $anumber};
            }
        }

        // We might have custom int.
        for ($anumber = 1; $anumber <= constants::MAXCUSTOMINT; $anumber++) {
            if (property_exists($data, constants::CUSTOMINT . $anumber)) {
                $theitem->{constants::CUSTOMINT . $anumber} = $data->{constants::CUSTOMINT . $anumber};
            }
        }

        // Now update the db once we have saved files and stuff.
        if (!$DB->update_record(constants::M_QTABLE, $theitem)) {
            $ret->error = true;
            $ret->message = "Could not update minilesson item!";
            return $ret;
        } else {
            $ret->item = $theitem;
            return $ret;
        }
    } // End of edit_insert_question.

    public static function delete_item($itemid, $context) {
        global $DB;
        $ret = false;

        if (!$DB->delete_records(constants::M_QTABLE, ['id' => $itemid])) {
            print_error("Could not delete item");
            return $ret;
        }
        // remove files
        $fs = get_file_storage();

        $fileareas = [
            constants::TEXTPROMPT_FILEAREA,
            constants::TEXTPROMPT_FILEAREA . '1',
            constants::TEXTPROMPT_FILEAREA . '2',
            constants::TEXTPROMPT_FILEAREA . '3',
            constants::TEXTPROMPT_FILEAREA . '4',
            constants::MEDIAQUESTION,
        ];

        foreach ($fileareas as $filearea) {
            $fs->delete_area_files($context->id, constants::M_COMPONENT, $filearea, $itemid);
        }
        $ret = true;
        return $ret;
    }


    public static function fetch_editor_options($course, $modulecontext) {
        $maxfiles = 99;
        $maxbytes = $course->maxbytes;
        return [
            'trusttext' => 0,
            'noclean' => 1,
            'subdirs' => true,
            'maxfiles' => $maxfiles,
            'maxbytes' => $maxbytes,
            'context' => $modulecontext,
        ];
    }

    public static function fetch_filemanager_options($course, $maxfiles = 1) {
        $maxbytes = $course->maxbytes;
        return ['subdirs' => true, 'maxfiles' => $maxfiles, 'maxbytes' => $maxbytes, 'accepted_types' => ['audio', 'video', 'image']];
    }

    // fetch the next item order in the list of items
    protected static function fetch_next_item_order($minilessonid) {
        global $DB;

        $allitems = $DB->get_records(constants::M_QTABLE, ['minilesson' => $minilessonid], 'itemorder ASC');
        if ($allitems && count($allitems) > 0) {
            $lastitem = array_pop($allitems);
            $itemorder = $lastitem->itemorder + 1;
        } else {
            $itemorder = 1;
        }
        return $itemorder;
    }

    // creates a "unique" item key so that backups and restores won't stuff things
    public static function create_itemkey() {
        global $CFG;
        $prefix = $CFG->wwwroot . '@';
        return uniqid($prefix, true);
    }



    /*
     * Remove any accents and chars that would mess up the transcript//passage matching
     */
    public function deaccent() {
        if (
            $this->needsspeechrec && isset($this->itemrecord->customtext1)
            && !empty($this->itemrecord->customtext1)
        ) {
            $this->itemrecord->customtext1 = utils::remove_accents_and_poormatchchars($this->itemrecord->customtext1, $this->moduleinstance->ttslanguage);
        }
    }


    public function update_create_langmodel($olditemrecord) {
        // if we need to generate a Coqui model for this, then lets do that now:
        // we want to process the hashcode and lang model if it makes sense
        $newitem = $this->itemrecord;
        $passage = isset($newitem->customtext1) ? $newitem->customtext1 : '';
        if ($this->needsspeechrec && !empty($passage)) {
            if (utils::needs_lang_model($this->moduleinstance, $passage)) {
                // lets assign a default passage hash
                if ($olditemrecord) {
                    $this->itemrecord->passagehash = $olditemrecord->passagehash;
                } else {
                    $this->itemrecord->passagehash = "";
                }

                // then fetch a new passage hash and see if we need to update it on the servers
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
            } else {
                $this->itemrecord->passagehash = '';
            }
        } else {
            $this->itemrecord->passagehash = '';
        }
        return false;
    }

    // we want to generate a phonetics if this is phonetic'able
    public function update_create_phonetic($olditemrecord) {
        // if we have an old item, set the default return value to the current phonetic value
        // we will update it if the text has changed
        $newitem = $this->itemrecord;
        if ($olditemrecord) {
            $thephonetics = $olditemrecord->phonetic;
        } else {
            $thephonetics = '';
        }
        $newpassage = isset($newitem->customtext1) ? $newitem->customtext1 : '';
        if ($this->needsspeechrec && !empty($newpassage)) {
            if ($olditemrecord !== false) {
                $oldpassage = $olditemrecord->customtext1;
            } else {
                $oldpassage = '';
            }

            if ($newpassage !== $oldpassage) {
                $segmented = true;
                $sentences = explode(PHP_EOL, $newpassage);
                $allphonetics = [];
                foreach ($sentences as $sentence) {
                    list($thephones, $thesegments) = utils::fetch_phones_and_segments($sentence, $this->language, 'tokyo', $segmented);
                    if (!empty($thephones)) {
                        $allphonetics[] = $thephones . '|#' . $thesegments;
                    }
                }

                // build the final phonetics
                if (count($allphonetics) > 0) {
                    $thephonetics = implode(PHP_EOL, $allphonetics);
                }
            }
        }
        $this->itemrecord->phonetic = $thephonetics;
        return $thephonetics;
    }

    function time_to_seconds($timestamp) {
        $timestamp = utils::super_trim($timestamp);
        if (preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $timestamp, $matches)) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];
            $seconds = (int) $matches[3];

            if ($minutes < 60 && $seconds < 60) {
                return $hours * 3600 + $minutes * 60 + $seconds;
            }
        }
        return false; // Malformed string
    }

    final public static function get_itemname($subpluginname = constants::SUBPLUGINTYPES['item']) {
        return ltrim(str_replace($subpluginname, '', static::get_component()), '_');
    }

    final public static function get_component() {
        return utils::get_component(static::class);
    }

    public function get_template_name(\renderer_base $renderer): string {
        return static::get_component() . '/' . static::get_itemname();
    }

    public function prepare_instructions_for_ai_grade(stdClass $instructions) {
    }

    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $result->hascorrectanswer = false;
        $result->hasincorrectanswer = false;
        $result->hasanswerdetails = false;
        $result->correctans = [];
        $result->incorrectans = [];
    }

    /**
     * Is the community page (shared student submissions) on for this item?
     * Item types that support it (e.g. free speaking) override this.
     *
     * @return bool
     */
    public function community_page_enabled() {
        return false;
    }

    /**
     * Are likes allowed on this item's community page?
     * Item types that support it (e.g. free speaking) override this.
     *
     * @return bool
     */
    public function community_likes_enabled() {
        return false;
    }

    /**
     * The minimum step grade (percent) for a submission to be eligible for
     * this item's community page. Item types that make it configurable
     * (e.g. free speaking) override this.
     *
     * @return int
     */
    public function community_eligibility_grade() {
        return \mod_minilesson\local\cpage::ELIGIBLE_GRADE;
    }

    /**
     * Does a community page submission for this item need an audio recording?
     * True for spoken item types (the recording is the submission); written
     * item types (e.g. free writing) override this to return false, and their
     * community page shows the text with a "more" link instead.
     *
     * @return bool
     */
    public function community_needs_media() {
        return true;
    }

    /**
     * Is this item type experimental?
     * Experimental item types are only available when $CFG->minilesson_experimental is set.
     * Item types that are not ready for general use override this to return true.
     *
     * @return bool
     */
    public static function is_experimental() {
        return false;
    }

    public static function is_configured() {
        global $CFG;
        if (static::is_experimental()) {
            return !empty($CFG->minilesson_experimental);
        }
        return true;
    }

    /**
     * When the item's layout is set to "auto", a big content element (text, image, video,
     * iframe or YouTube clip) switches the item to horizontal layout. Item types whose content
     * should stay stacked vertically no matter how much of it there is (e.g. content pages)
     * override this to return true.
     *
     * @return bool
     */
    public function autolayout_prefers_vertical() {
        return false;
    }

    /**
     * Is this item type's response a spoken one, for the purposes of AI grading?
     * True for spoken item types (the recording is graded); written item types
     * (e.g. free writing) override this to return false.
     *
     * @return bool
     */
    public static function ai_grade_uses_speech() {
        return true;
    }

    /**
     * Does this item type collect an extended piece of student work (a spoken or written
     * response) that later items can be given as context? Item types that do (e.g. free
     * speaking, free writing) override this to return true.
     *
     * @return bool
     */
    public static function produces_student_text() {
        return false;
    }

    /**
     * Can this item type's content be sent for translation?
     * Item types that offer translation natively (e.g. fiction) override this to return true.
     * Note that any item carrying a TTS dialog media prompt is translatable regardless.
     *
     * @return bool
     */
    public static function supports_translation() {
        return false;
    }

    /**
     * Add anything this item type needs on a page that displays or edits it - most often CSS or
     * JS shipped by the item type itself. Called once per item type present in the lesson.
     * Item types with such requirements override this.
     *
     * @param \moodle_page $page The page to add requirements to.
     * @return void
     */
    public static function page_requirements(\moodle_page $page) {
    }

    /**
     * Should this item type's splash screen and activity wrapper be drawn as a centred white
     * card (shadow, rounded corners) rather than filling the item area? Item types with a splash
     * screen generally want this, and override it to return true.
     *
     * @return bool
     */
    public function uses_boxed_layout() {
        return false;
    }

    /**
     * Render a preview of this item from unsaved authoring form data, for the "preview" button
     * on the item's authoring form. Item types that offer a preview (e.g. slides) override this;
     * the default is no preview.
     *
     * @param array $formdata The parsed authoring form data.
     * @return string HTML fragment, or '' for no preview.
     */
    public static function render_preview($formdata) {
        return '';
    }

    /**
     * Return the plain text of this item, for use by the speech test (which needs a sample of
     * the lesson's language content). Item types whose text is not simply the item text
     * (e.g. the gapfill types, whose text carries gap markup) override this.
     *
     * @param \stdClass $itemrecord The item's DB record.
     * @param string $default The text the caller derived from the item record.
     * @return string
     */
    public function get_speechtester_text($itemrecord, $default) {
        return $default;
    }

    public static function get_plugininfo(): minilessonitem {
        return core_plugin_manager::instance()->get_plugin_info(static::get_component());
    }

}
