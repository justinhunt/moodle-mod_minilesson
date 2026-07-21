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

namespace minilessonitem_multichoice;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a multichoice item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {

    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_READING, constants::SKILL_LISTENING];

    public const SHUFFLEANSWER = 'customint5';
    public const CORRECTFEEDBACK = 'customtext6';
    public const HIDEANSWERTEXT = 'customint6';
    public const ANSWERLAYOUT = 'customint7';
    public const ANSWERLAYOUT_DEFAULT = 1;
    public const ANSWERLAYOUT_TWOCOLUMN = 2;
    public const HIDEANSWER_NO = 0;
    public const HIDEANSWER_YES = 1;
    public const HIDEANSWER_ABCD = 2;
    /** @var string Setting: hide the question text during the quiz (it still shows with the results). */
    public const HIDEQUESTIONTEXT = 'customint11';

    /** @var int Show the question text (default). */
    public const HIDEQUESTION_NO = 0;

    /** @var int Hide the question text during the quiz. */
    public const HIDEQUESTION_YES = 1;

    // the item type
    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        $itemrecord = $this->itemrecord;
        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);
        // Multichoice also needs sentences if we are listening. Its a bit of double up but we do that here.
        $testitem->sentences = [];
        $testitem->imagecontent = false;
        $testitem->audiocontent = false;
        $testitem->hideanswertext = $itemrecord->{self::HIDEANSWERTEXT};
        $testitem->answerlayout = $itemrecord->{self::ANSWERLAYOUT};

        // The question may be spoken in the item audio, in which case the question text can be hidden.
        // The quiz finished results screen builds its question text from the db record, so it still shows there.
        if (!empty($itemrecord->{self::HIDEQUESTIONTEXT})) {
            unset($testitem->itemtext);
        }

        $testitem->layoutclassname = '';
        if ($itemrecord->{constants::LISTENORREAD} != constants::LISTENORREAD_IMAGE && $testitem->answerlayout == self::ANSWERLAYOUT_TWOCOLUMN) {
            $testitem->layoutclassname = "multichoice_twocolumnlayout";
        }

        switch ($itemrecord->{constants::LISTENORREAD}) {
            case constants::LISTENORREAD_LISTEN:
            case constants::LISTENORREAD_LISTENANDREAD:
                $testitem->audiocontent = true;
                break;
            case constants::LISTENORREAD_IMAGE:
                $testitem->imagecontent = true;
                break;
        }

        // Sentences.
        $sentences = [];
        if (isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->{constants::TEXTANSWER . 1});
        }
        // Image URLs.
        $imageurls = [];
        if ($testitem->imagecontent) {
            $imageurls = $this->fetch_sentence_media('image', 1);
        }
        // Audio URLs.
        $audiourls = [];
        if ($testitem->audiocontent) {
            $audiourls = $this->fetch_sentence_media('audio', 1);
        }

        for ($anumber = 1; $anumber <= constants::MAXANSWERS; $anumber++) {
            $theimageurl = '';
            $theaudiourl = '';
            $sentencetext = '';

            // If we have a sentence, we fetch it.
            if (isset($sentences[$anumber - 1]) && !empty(trim($sentences[$anumber - 1]))) {
                $sentencetext = trim($sentences[$anumber - 1]);
            }

            // If we have an image, we fetch it.
            if ($testitem->imagecontent) {
                if (isset($imageurls[$anumber]) && !empty($imageurls[$anumber])) {
                    $theimageurl = $imageurls[$anumber];
                }
            }

            // If we have an audio, we fetch it.
            if ($testitem->audiocontent) {
                if (isset($audiourls[$anumber]) && !empty($audiourls[$anumber])) {
                    $theaudiourl = $audiourls[$anumber];
                } else {
                    // If we have no custom audio then we use the polly audio.
                    if (!empty($sentencetext)) {
                        $theaudiourl = utils::fetch_polly_url(
                            $this->token,
                            $this->region,
                            $sentencetext,
                            $this->itemrecord->{constants::POLLYOPTION},
                            $this->itemrecord->{constants::POLLYVOICE},
                            $this->moduleinstance->id
                        );
                    }
                }
            }

            // If we have a sentence or an image, we add an answer to the mustache template data.
            if (!empty($sentencetext) || !empty($theimageurl)) {
                $sentence = $sentencetext;

                $s = new \stdClass();
                $s->index = $anumber - 1;
                $s->indexplusone = $anumber;
                $s->sentence = $sentence;
                $s->length = \core_text::strlen($sentence);

                if ($itemrecord->{constants::LISTENORREAD} == constants::LISTENORREAD_LISTEN) {
                    $s->prompt = $this->dottify_text($sentence);
                } else {
                    switch($testitem->hideanswertext) {
                        case self::HIDEANSWER_NO:
                            $s->prompt = $sentence;
                            break;
                        case self::HIDEANSWER_YES:
                            $s->prompt = '';
                            break;
                        case self::HIDEANSWER_ABCD:
                            // In listening tests we may just show A, B, C or D in place of the text, so we prepare that here.
                            // In that case shuffleanswer would shuffle the order of A, B, C or D. - so shuffling is suppressed in this mode
                            $abcd = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                            $s->prompt = $abcd[$anumber - 1];
                            break;
                    }
                }
                if (!empty($theimageurl)) {
                    $s->imageurl = $theimageurl;
                }
                if (!empty($theaudiourl)) {
                    $s->audiourl = $theaudiourl;
                }
                $testitem->sentences[] = $s;
            }
        }

        // Question Point
        // Rich text feedback explaining the correct answer.
        $testitem->correctfeedback = $itemrecord->{self::CORRECTFEEDBACK};

        // Multichoice also has a confirm choice option we need to include.
        $testitem->confirmchoice = $itemrecord->{constants::CONFIRMCHOICE};
        // In A,B,C,D mode the on-screen letters must keep the order the author (and any audio) gave them,
        // so shuffling is suppressed.
        $testitem->shuffleanswers = !empty($itemrecord->{self::SHUFFLEANSWER})
            && $testitem->hideanswertext != self::HIDEANSWER_ABCD;
        return $testitem;
    }

    /**
     * Validates an import record for this item type. Runs after preprocessing, so int fields are
     * already cast and any payload files are attached to the record as filearea arrays.
     *
     * @param \stdClass $newrecord the db-ready import record
     * @param \stdClass $cm the course module
     * @return false|\stdClass false when valid, or an error object with col and message
     */
    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        // Answers may be text lines in customtext1, or answer images (attached to the record when the
        // import payload contained files for the customfile1_image area), or both. Image only answers are valid.
        $answerlines = array_map('trim', explode(PHP_EOL, (string) $newrecord->customtext1));
        $answers = array_filter($answerlines, function ($answer) {
            return $answer !== '';
        });
        $imagefield = constants::FILEANSWER . '1_image';
        $imagecount = isset($newrecord->{$imagefield}) ? count((array) $newrecord->{$imagefield}) : 0;

        if (count($answers) == 0 && $imagecount == 0) {
            $error->col = 'customtext1';
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }
        if (count($answers) > constants::MAXANSWERS) {
            $error->col = 'customtext1';
            $error->message = get_string('error:toomanyanswers', constants::M_COMPONENT, constants::MAXANSWERS);
            return $error;
        }

        // Answers render at their 1-based line position (a blank line keeps later answers in place) and
        // answer images render at their numeric filename ("3.png" is answer 3), so correctanswer must point
        // at a position that actually holds an answer - matching how indexplusone is graded at runtime.
        $imagenumbers = [];
        if ($imagecount > 0) {
            foreach (array_keys((array) $newrecord->{$imagefield}) as $filename) {
                $imagenumbers[] = (int) pathinfo($filename, PATHINFO_FILENAME);
            }
        }
        $maxanswerpos = $imagenumbers ? max($imagenumbers) : 0;
        foreach ($answerlines as $i => $line) {
            if ($line !== '') {
                $maxanswerpos = max($maxanswerpos, $i + 1);
            }
        }
        $correctpos = (int) $newrecord->correctanswer;
        $hasanswer = (isset($answerlines[$correctpos - 1]) && $answerlines[$correctpos - 1] !== '')
            || in_array($correctpos, $imagenumbers, true);
        if ($correctpos < 1 || !$hasanswer) {
            $error->col = 'correctanswer';
            $error->message = get_string('error:correctanswerrange', constants::M_COMPONENT, $maxanswerpos);
            return $error;
        }

        // Option value checks: reject impossible values. Absent fields arrive here as their column
        // defaults (which all pass), and 0 is tolerated for answerlayout because empty CSV cells
        // arrive as 0 and render as the default layout.
        $optionchecks = [
            constants::LISTENORREAD => [constants::LISTENORREAD_READ, constants::LISTENORREAD_LISTEN,
                constants::LISTENORREAD_LISTENANDREAD, constants::LISTENORREAD_IMAGE],
            self::HIDEANSWERTEXT => [self::HIDEANSWER_NO, self::HIDEANSWER_YES, self::HIDEANSWER_ABCD],
            self::ANSWERLAYOUT => [0, self::ANSWERLAYOUT_DEFAULT, self::ANSWERLAYOUT_TWOCOLUMN],
            self::SHUFFLEANSWER => [0, 1],
        ];
        foreach ($optionchecks as $col => $allowed) {
            if (isset($newrecord->{$col}) && !in_array((int) $newrecord->{$col}, $allowed)) {
                $error->col = $col;
                $error->message = get_string(
                    'error:invalidoptionvalue',
                    constants::M_COMPONENT,
                    ['value' => $newrecord->{$col}, 'allowed' => implode(',', $allowed)]
                );
                return $error;
            }
        }

        // return false to indicate no error
        return false;
    }

    /*
     * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
     */
    public static function get_keycolumns() {
        // get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['text5'] = ['jsonname' => 'promptvoice', 'type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYVOICE];
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt', 'type' => 'voiceopts', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYOPTION];
        $keycols['int3'] = ['jsonname' => 'confirmchoice', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::CONFIRMCHOICE];
        $keycols['int2'] = ['jsonname' => 'listenorread', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::LISTENORREAD]; // not boolean ..
        $keycols['text1'] = ['jsonname' => 'answers', 'type' => 'stringarray', 'optional' => false, 'default' => [], 'dbname' => 'customtext1'];
        $keycols['text6'] = ['jsonname' => 'correctfeedback', 'type' => 'string', 'optional' => true, 'default' => '', 'dbname' => self::CORRECTFEEDBACK];
        $keycols['fileanswer_audio'] = ['jsonname' => constants::FILEANSWER . '1_audio', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        $keycols['fileanswer_image'] = ['jsonname' => constants::FILEANSWER . '1_image', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        $keycols['int5'] = ['jsonname' => 'shuffleanswer', 'type' => 'int', 'optional' => true, 'default' => null, 'dbname' => self::SHUFFLEANSWER];
        $keycols['int6'] = ['jsonname' => 'hideanswertext', 'type' => 'int', 'optional' => true, 'default' => null, 'dbname' => self::HIDEANSWERTEXT];
        $keycols['int7'] = ['jsonname' => 'answerlayout', 'type' => 'int', 'optional' => true, 'default' => self::ANSWERLAYOUT_DEFAULT, 'dbname' => self::ANSWERLAYOUT];
        $keycols['int11'] = ['jsonname' => 'hidequestiontext', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => self::HIDEQUESTIONTEXT];
        return $keycols;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'A single multiple choice question with 2 to ' . constants::MAXANSWERS . ' answer options, '
            . 'exactly one of which is correct. There is an optional media prompt (tts audio, tts dialog or an uploaded file), '
            . 'and the answer options can be text, text read aloud by TTS, or images. '
            . 'Use it as a comprehension check, a check on the meaning of a word or phrase, or - '
            . 'with spoken answer options, or a tts dialog and A,B,C,D answer labels - as a listening exercise. '
            . 'For several questions about one shared passage or audio, prefer multichoicequiz.';
    }

    /**
     * The agent-facing import field spec for multichoice. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $ttsoptions = [
            ['value' => 'normal', 'meaning' => 'Normal speed (default; any unrecognised value also maps to normal)'],
            ['value' => 'slow', 'meaning' => 'Slow reading speed'],
            ['value' => 'veryslow', 'meaning' => 'Very slow reading speed'],
            ['value' => 'SSML', 'meaning' => 'Treat the text as SSML markup (this value is case-sensitive)'],
        ];

        // Shared fields (type/required/default seeded from get_keycolumns; prose from the base catalog).
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions', 'text',
            'textarea',
            'tts', 'ttsvoice', 'ttsoption', 'ttsautoplay',
            'ttsdialog', 'ttsdialogvoicea', 'ttsdialogvoiceb', 'ttsdialogvoicec',
            'ttsdialoglabela', 'ttsdialoglabelb', 'ttsdialoglabelc', 'ttsdialogvisible',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'multichoice';
        $fields['text']['description'] = 'The question text, shown as a short centered heading above the answers, '
            . 'so keep it to a single line. Can be omitted when the question is delivered by the tts audio or '
            . 'ttsdialog. For multi-line question content such as a two-speaker dialog, put it in "textarea" '
            . '(the text block) instead, which is left-aligned and preserves line breaks.';

        // Multichoice specific fields.
        $ownfields = [
            'answers' => [
                'description' => 'The answer options, as an array of up to ' . constants::MAXANSWERS . ' strings. '
                    . 'In listen modes (listenorread=1 or 2) each option is also read aloud by the promptvoice. '
                    . 'In image answer mode (listenorread=3) the images are the answer options and this array '
                    . 'may contain empty strings.',
                'example' => '["He drinks both cans.", "He stands on them to reach the shelf.", "He gives them to a friend."]',
            ],
            'correctanswer' => [
                'required' => true,
                'description' => 'The 1-based index of the correct answer option '
                    . '(counting answer images in image answer mode).',
                'example' => '2',
            ],
            'listenorread' => [
                'description' => 'How the answer options are presented to the learner.',
                'options' => [
                    ['value' => (string) constants::LISTENORREAD_READ,
                        'meaning' => 'Read: options are shown as plain text (default)'],
                    ['value' => (string) constants::LISTENORREAD_LISTEN,
                        'meaning' => 'Listen: each option is played as TTS audio read by promptvoice, '
                            . 'and its text is masked with dots'],
                    ['value' => (string) constants::LISTENORREAD_LISTENANDREAD,
                        'meaning' => 'Listen and read: TTS audio players plus the option text'],
                    ['value' => (string) constants::LISTENORREAD_IMAGE,
                        'meaning' => 'Image: the answer options are images supplied in the customfile1_image file area'],
                ],
            ],
            'promptvoice' => [
                'description' => 'The TTS voice that reads the answer options aloud in listen modes. '
                    . 'A voice display name (case-insensitive), e.g. "Joey" (en-US) or "Mathieu" (fr-FR), '
                    . 'or "auto" to let the server pick a voice matching the lesson language.',
                'example' => 'auto',
            ],
            'promptvoiceopt' => [
                'description' => 'Reading speed / processing option for the answer option TTS audio.',
                'options' => $ttsoptions,
            ],
            'confirmchoice' => [
                'description' => 'Whether the learner must answer to move on (no skipping the question).',
                'options' => [
                    ['value' => '0', 'meaning' => 'The learner may skip the question (default)'],
                    ['value' => '1', 'meaning' => 'The learner must choose an answer'],
                ],
            ],
            'shuffleanswer' => [
                'description' => 'Whether the display order of the answer options is shuffled. '
                    . 'Ignored (never shuffled) when hideanswertext=2, so that the A,B,C,D labels keep the authored order.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Keep the authored order'],
                    ['value' => '1', 'meaning' => 'Shuffle the answer options'],
                ],
            ],
            'hideanswertext' => [
                'description' => 'Controls display of the answer option text.',
                'options' => [
                    ['value' => (string) self::HIDEANSWER_NO,
                        'meaning' => 'Show the answer text (default)'],
                    ['value' => (string) self::HIDEANSWER_YES,
                        'meaning' => 'Hide the answer text entirely, e.g. when the options are images'],
                    ['value' => (string) self::HIDEANSWER_ABCD,
                        'meaning' => 'Replace the answer text with the labels A,B,C,D, e.g. for listening questions '
                            . 'where the audio refers to the options by letter; shuffleanswer is ignored in this mode'],
                ],
                'example' => '2',
            ],
            'answerlayout' => [
                'description' => 'Layout of the answer options.',
                'options' => [
                    ['value' => (string) self::ANSWERLAYOUT_DEFAULT, 'meaning' => 'Single column (default)'],
                    ['value' => (string) self::ANSWERLAYOUT_TWOCOLUMN, 'meaning' => 'Two column grid'],
                ],
            ],
            'hidequestiontext' => [
                'description' => 'Hide the question text during the quiz, e.g. when the tts audio or ttsdialog '
                    . 'delivers the question. The text still shows on the results screen.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Show the question text (default)'],
                    ['value' => '1', 'meaning' => 'Hide the question text'],
                ],
            ],
            'correctfeedback' => [
                'description' => 'Feedback shown to the learner after answering, explaining the correct answer.',
                'example' => 'He stands on the cans to reach the shelf - watch the last scene again.',
            ],
        ];
        foreach ($ownfields as $jsonname => $overlay) {
            $fields[$jsonname] = static::aigen_seed_field_spec($jsonname, $overlay);
        }

        // Pseudo field: filesid is a payload convention, not a keycolumn.
        $fields['filesid'] = [
            'jsonname' => 'filesid',
            'type' => 'int',
            'required' => false,
            'default' => '',
            'description' => 'Links this item to its entry in the top level "files" object of the payload. '
                . 'Only needed when the item has a question media prompt, answer images or uploaded answer audio.',
            'example' => '1',
        ];

        // A tiny but valid 1x1 png, so the example files payload is genuinely importable.
        $onepixelpng = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        return [
            'usage' => 'Compose one item object per question. Supply between 2 and ' . constants::MAXANSWERS
                . ' answer options and exactly one correctanswer index. For a reading check use listenorread=0; '
                . 'for a listening check use listenorread=1 (options read aloud by promptvoice) or a ttsdialog with '
                . 'hideanswertext=2 so the audio can refer to options A,B,C,D; for picture questions use listenorread=3 '
                . 'with images in the customfile1_image file area. You can also show a picture, audio or video '
                . 'prompt above the question via the ' . constants::MEDIAQUESTION . ' file area.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::MEDIAQUESTION,
                    'description' => 'An uploaded image, audio or video shown as the question media prompt, above '
                        . 'the answer options.',
                    'filenames' => 'Any single filename; the extension decides how it renders: '
                        . 'image (png/jpg/gif/svg), video (mp4/mov/webm) or audio (mp3/m4a/ogg/wav).',
                ],
                [
                    'filearea' => constants::FILEANSWER . '1_audio',
                    'description' => 'Uploaded audio for the answer options (overrides the promptvoice TTS audio '
                        . 'in listen modes). Usually unnecessary: prefer TTS via promptvoice.',
                    'filenames' => 'Name each file for its 1-based answer index: "1.mp3" .. "'
                        . constants::MAXANSWERS . '.mp3".',
                ],
                [
                    'filearea' => constants::FILEANSWER . '1_image',
                    'description' => 'Images used as the answer options (with listenorread=3).',
                    'filenames' => 'Name each file for its 1-based answer index: "1.png" .. "'
                        . constants::MAXANSWERS . '.png" (.jpg is also fine).',
                ],
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'multichoice',
                        'name' => 'Comprehension check',
                        'instructions' => 'Choose the correct answer.',
                        'text' => 'What does the boy do with the two cans at the end of the story?',
                        'answers' => [
                            'He drinks both cans.',
                            'He stands on them to reach the shelf.',
                            'He gives them to a friend.',
                        ],
                        'correctanswer' => 2,
                        'listenorread' => constants::LISTENORREAD_READ,
                        'shuffleanswer' => 1,
                        'correctfeedback' => 'He stands on the cans to reach the shelf.',
                    ],
                    [
                        'type' => 'multichoice',
                        'name' => 'Picture question',
                        'instructions' => 'Choose the correct picture.',
                        'text' => 'Which picture shows a cat?',
                        'answers' => ['', ''],
                        'correctanswer' => 1,
                        'listenorread' => constants::LISTENORREAD_IMAGE,
                        'hideanswertext' => self::HIDEANSWER_YES,
                        'filesid' => 1,
                    ],
                ],
                'files' => [
                    '1' => [
                        constants::FILEANSWER . '1_image' => [
                            '1.png' => $onepixelpng,
                            '2.png' => $onepixelpng,
                        ],
                    ],
                ],
            ],
        ];
    }

    /*
     * This function return the prompt that the generate method requires for multichoice.
     */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Create a multichoice question(text) and a one dimensional array of 4 answers (answers) in {language} suitable for {level} level learners to test the learner's understanding of the following passage: [{text}] ";
                $prompt .= "Also specify the correct answer as a number 1-4 in 'correctanswer'. ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Create a multichoice question(text) and a one dimensional array of 4 answers (answers) in {language} suitable for {level} level learners on the topic of: [{topic}] ";
                $prompt .= "Also specify the correct answer as a number 1-4 in 'correctanswer'. ";
                break;
        }
        return $prompt;
    }

    public function upgrade_item($oldversion) {
        global $DB;

        $success = true;

        if ($oldversion < 2025071305) {
            // The original multichoice stored each answer in a separate field.
            // We need to convert that to the new format which is a single field with answers separated
            // by a newline character. And we need to handle any images that were uploaded
            // as separate files in the file area to a single file area and named as 1.jpg, 2.jpg, etc.
            $sentences = [];
            $imagecontent = $this->itemrecord->{constants::LISTENORREAD} == constants::LISTENORREAD_IMAGE;
            $sentencefieldindex = 1;
            $mediatype = "image";

            // We do a quick check to see if this item has already been upgraded, in which case we will skip the upgrade.
            if (
                !isset($this->itemrecord->{constants::TEXTANSWER . 2}) ||
                empty(utils::super_trim($this->itemrecord->{constants::TEXTANSWER . 2}))
            ) {
                // This item has already been upgraded, we can skip the upgrade.
                return $success;
            }

            for ($anumber = 1; $anumber <= constants::MAXANSWERS; $anumber++) {
                // If we have a sentence, we fetch it, and then clear the field.
                if (!empty(utils::super_trim($this->itemrecord->{constants::TEXTANSWER . $anumber}))) {
                    $sentences[] = utils::super_trim($this->itemrecord->{constants::TEXTANSWER . $anumber});
                    $this->itemrecord->{constants::TEXTANSWER . $anumber} = '';
                }

                if ($imagecontent) {
                    $fs = get_file_storage();
                    $files = $fs->get_area_files($this->context->id, constants::M_COMPONENT, constants::FILEANSWER . $anumber, $this->itemrecord->id);

                    foreach ($files as $file) {
                        $filename = $file->get_filename();
                        if ($filename == '.') {
                            continue;
                        }
                        $filerecord = new \stdClass();
                        $filerecord->filearea = constants::FILEANSWER . $sentencefieldindex . '_' . $mediatype;
                        // Replace filename with number, eg banana.jpg becomes 1.jpg.
                        $filerecord->filename = $anumber . '.' . pathinfo($file->get_filename(), PATHINFO_EXTENSION);
                        $fs->create_file_from_storedfile($filerecord, $file);
                        // Now we can delete the old file.
                        $fs->delete_area_files($this->context->id, constants::M_COMPONENT, constants::FILEANSWER . $anumber, $this->itemrecord->id);
                        // We only want the first file so we break out of the loop.
                        break;
                    }
                }
            }
            $allsentences = implode(PHP_EOL, $sentences);
            $this->itemrecord->{constants::TEXTANSWER . 1} = $allsentences;
            $success = $DB->update_record(constants::M_QTABLE, $this->itemrecord);
        }

        return $success;
    }

    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $result->hascorrectanswer = true;
        $result->hasincorrectanswer = true;
        if (!empty($itemquizdata->correctfeedback)) {
            $result->hasanswerdetails = true;
            $result->resultsdatajson = json_encode(['correctfeedback' => $itemquizdata->correctfeedback]);
            $result->resultstemplate = self::get_component() . '/multichoiceresults';
        } else {
            $result->hasanswerdetails = false;
        }
        $correctanswers = [];
        $incorrectanswers = [];
        $correctindex = $itemquizdata->correctanswer;

        foreach ($itemquizdata->sentences as $sentance) {
            if ($correctindex == $sentance->indexplusone) {
                $correctanswers[] = $sentance->sentence;
            } else {
                $incorrectanswers[] = $sentance->sentence;
            }
        }

        if (count($correctanswers) == 0) {
            $result->hascorrectanswer = false;
        }
        if (count($incorrectanswers) == 0) {
            $result->hasincorrectanswer = false;
        }

        $result->correctans = ['sentence' => join(' ', $correctanswers)];
        $result->incorrectans = ['sentence' => join('<br> ', $incorrectanswers)];
    }

}
