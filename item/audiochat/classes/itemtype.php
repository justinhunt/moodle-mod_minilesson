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

namespace minilessonitem_audiochat;

use mod_minilesson\constants;
use mod_minilesson\local\itemtype\item;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for an audiochat item in a minilesson activity.
 *
 * @package    minilessonitem_audiochat
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_SPEAKING, constants::SKILL_LISTENING];

    /**
     * This item type has a splash screen, drawn as a centred white card.
     *
     * @return bool
     */
    public function uses_boxed_layout() {
        return true;
    }

    /** Default image avatar */
    public const DEFAULT_AVATAR = 'cutepoodll_small.png';

    public const INSTRUCTIONS = 'customtext6';

    public const FEEDBACKINSTRUCTIONS = 'customdata3';

    public const ROLE = 'customtext2';

    public const VOICE = 'customtext3';

    public const NATIVE_LANGUAGE = 'customtext4';

    public const TOPIC = 'customtext5';

    public const AIDATA1  = 'customdata1';

    public const AIDATA2  = 'customdata2';

    public const AUTORESPONSE = 'customint4';

    public const ALLOWRETRY  = 'customint5';

    public const INSTRUCTIONSSELECTION = 'customint6';

    public const FEEDBACKSELECTION = 'customint7';

    public const STUDENT_SUBMISSION = 'customint8';

    public const AUDIOAVATAR = 'customtext7';

    /** @var string */
    public const PROVIDER_GEMINI = 'gemini';

    /** @var string */
    public const PROVIDER_OPENAI = 'openai';

    /** @var string */
    public const PROVIDER_CLOUDPOODLL = 'cloudpoodll';

    /**
     * The class constructor.
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
        $testitem->itisningxiaregion = false;
        $provider = get_config(constants::M_COMPONENT, 'provider') ?: self::PROVIDER_CLOUDPOODLL;

        // Lets see if we can chat
        $testitem->canchat = false;
        if ($provider == self::PROVIDER_OPENAI) {
            $apikey = get_config(constants::M_COMPONENT, 'openaikey');
            $testitem->provider = get_string('openai', self::get_component());
            $testitem->canchat = !empty($apikey);
        } else if ($provider == self::PROVIDER_GEMINI) {
            $apikey = get_config(constants::M_COMPONENT, 'geminiapikey');
            $testitem->provider = get_string('gemini', self::get_component());
            $testitem->canchat = !empty($apikey);
        } else if ($provider == self::PROVIDER_CLOUDPOODLL) {
            $testitem->provider = get_string('cloudpoodll', self::get_component());
            $testitem->canchat = true;
        } else {
            $testitem->provider = get_string('unknown', self::get_component());
            $testitem->canchat = false;
        }

        // If we add a cloud poodll recorder to the page these are also added, but here we just add them manually.
        $testitem->language = $this->language;
        $testitem->region = $this->region;
        // For now cloudpoodll = gemini as far as JS is concerned ..
        // But in PHP cloudpoodll means we fetch the token from cloud poodll (in geminilive.php)
        $testitem->chatprovider = ($provider == self::PROVIDER_CLOUDPOODLL) ? self::PROVIDER_GEMINI : $provider;

        $testitem->itisningxiaregion = $this->region == 'ningxia';

        // Allow retry.
        $testitem->allowretry = $this->itemrecord->{self::ALLOWRETRY} == 1;

        // Replace the placeholders with what we know, first correcting missing placeholder data.
        if (empty($this->itemrecord->{self::ROLE})) {
            $this->itemrecord->{self::ROLE} = get_string('audiochat_role_default', constants::M_COMPONENT);
        }
        // Native language of the student.
        $nativelanguage = $this->itemrecord->{self::NATIVE_LANGUAGE};
        if ($nativelanguage == constants::AIGRADE_FEEDBACK_TARGET_LANGUAGE) {
            $nativelanguage = $this->moduleinstance->ttslanguage;
        } else if ($nativelanguage == constants::AIGRADE_FEEDBACK_NATIVE_LANGUAGE) {
            $nativelanguage = $this->moduleinstance->nativelang;
        }
        // If that did not work, set it en-US
        if (empty($nativelanguage)) {
            $nativelanguage = constants::M_LANG_ENUS;
        }
        $this->itemrecord->{self::NATIVE_LANGUAGE} = $nativelanguage;

        // Students native language - it is possible to use the one set in wordcards here also, so we check for that.
        $testitem->audiochatnativelanguage = $this->itemrecord->{self::NATIVE_LANGUAGE};
        if (get_config(constants::M_COMPONENT, 'setnativelanguage')) {
            $userprefnativelanguage = get_user_preferences(constants::NATIVELANG_PREF);
            if (!empty($userprefnativelanguage)) {
                $testitem->audiochatnativelanguage = $userprefnativelanguage;
            }
        }

        // In some cases teachers may not set the topic, so we need to handle that.
        // If the topic is empty, we check if the itemtext is set, otherwise we use 'student choice of topic'.
        if (empty($this->itemrecord->{self::TOPIC})) {
            if (!empty($this->itemrecord->itemtext)) {
                $this->itemrecord->{self::TOPIC} = $this->itemrecord->itemtext;
            } else {
                $this->itemrecord->{self::TOPIC} = 'student choice of topic';
            }
        }

        // The item text is what is shown to the student, the topic is what is passed to AI to be used in the prompt.
        // We need to show something to student, so if its empty we show the topic.
        if (empty($testitem->itemtext)) {
            $testitem->itemtext = $this->itemrecord->{self::TOPIC};
        }

        // Set up the audiochat instructions.
        $testitem->audiochatinstructions = $this->itemrecord->{self::INSTRUCTIONS};
        // If no topic was set, then we use the default topic.
        if (empty($testitem->audiochatinstructions)) {
            $testitem->audiochatinstructions = get_string('audiochat:instructionsprompt_dec1', constants::M_COMPONENT);
        }

        // Replace the placeholders in the audiochat instructions with the actual data.
        $testitem->audiochatinstructions = str_replace(
            [
                '{ai role}',
                '{ai voice}',
                '{native language}',
                '{target language}',
                '{topic}',
                '{ai data1}',
                '{ai data2}',
            ],
            [
                $this->itemrecord->{self::ROLE},
                $this->itemrecord->{self::VOICE},
                $testitem->audiochatnativelanguage,
                $this->language,
                $this->itemrecord->{self::TOPIC},
                $this->itemrecord->{self::AIDATA1},
                $this->itemrecord->{self::AIDATA2},
            ],
            $testitem->audiochatinstructions
        );

        // Set up the audiochat grade instructions.
        $testitem->audiochatgradeinstructions = $this->itemrecord->{self::FEEDBACKINSTRUCTIONS};
        if (!empty($testitem->audiochatgradeinstructions)) {
            $testitem->audiochatgradeinstructions = str_replace(
                [
                    '{ai role}',
                    '{ai voice}',
                    '{native language}',
                    '{target language}',
                    '{topic}',
                    '{ai data1}',
                    '{ai data2}',
                ],
                [
                    $this->itemrecord->{self::ROLE},
                    $this->itemrecord->{self::VOICE},
                    $testitem->audiochatnativelanguage,
                    $this->language,
                    $this->itemrecord->{self::TOPIC},
                    $this->itemrecord->{self::AIDATA1},
                    $this->itemrecord->{self::AIDATA2},
                ],
                $testitem->audiochatgradeinstructions
            );
        }

        // Set the Auto turn detection to on or off.
        $testitem->audiochat_autoresponse = $this->itemrecord->{self::AUTORESPONSE} ? true : false;

        // AI Voice.
        $testitem->audiochat_voice = $this->itemrecord->{self::VOICE};

        $testitem->totalmarks = $this->itemrecord->{constants::TOTALMARKS};
        if ($this->itemrecord->{constants::TARGETWORDCOUNT} > 0) {
            $testitem->targetwordcount = $this->itemrecord->{constants::TARGETWORDCOUNT};
            $testitem->countwords = true;
        } else {
            $testitem->countwords = false;
        }

        // Replace any template variables in the question text.
        if (!empty($testitem->itemtext)) {
            $search = ['{topic}', '{ai data1}', '{ai data2}'];
            $replace = [
                $this->itemrecord->{self::TOPIC},
                $this->itemrecord->{self::AIDATA1},
                $this->itemrecord->{self::AIDATA2},
            ];
            $testitem->itemtext = str_replace($search, $replace, $testitem->itemtext);
        }

        // We also want to show the question topic.
        $testitem->topic = $this->itemrecord->{self::TOPIC};

        // We might need cmid and itemid to do the AI evaluation by ajax.
        $testitem->itemid = $this->itemrecord->id;
        // Not sure if we need this.
        $testitem->maxtime = $this->itemrecord->timelimit;

        $imgaudioavatar = $this->itemrecord->{self::AUDIOAVATAR} ?
            $this->itemrecord->{self::AUDIOAVATAR} :
            self::DEFAULT_AVATAR;
        $testitem->avatarimage = $output->image_url(
            pathinfo($imgaudioavatar, PATHINFO_FILENAME),
            self::get_component()
        )->out(false);

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

        if (trim((string) $newrecord->{self::INSTRUCTIONS}) == '') {
            $error->col = self::INSTRUCTIONS;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        if (trim((string) $newrecord->{self::ROLE}) == '') {
            $error->col = self::ROLE;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        if (trim((string) $newrecord->{self::TOPIC}) == '') {
            $error->col = self::TOPIC;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }
        return false;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'A live spoken conversation with an AI chat partner: the learner talks with the AI about a topic, '
            . 'an image or their own earlier submission, and afterwards the AI grades the conversation and gives '
            . 'feedback. Use it for realistic conversation practice, guided question-and-answer about a picture '
            . 'or passage, or as a follow-up discussion of a freewriting/freespeaking answer. It is the most '
            . 'open-ended speaking item type; for controlled speaking practice use listenrepeat, speechcards '
            . 'or speakinggapfill.';
    }

    /**
     * The agent-facing import field spec for audiochat. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $placeholdernote = 'May contain the placeholders {ai role}, {ai voice}, {target language}, '
            . '{native language}, {ai data1}, {ai data2}, {student submission} and {topic}.';

        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions', 'text',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'audiochat';
        $fields['text']['description'] = 'Text shown above the chat, e.g. the question or topic. When empty, '
            . 'the audiochattopic is shown instead. NOTE: the AI cannot see this text (or the name/instructions) - '
            . 'anything the AI needs must be in audiochattopic, audiochatinstructions or the aidata fields.';

        $ownfields = [
            'audiochattopic' => [
                'description' => 'The discussion topic, inserted where {topic} appears in the AI instructions. '
                    . 'Also displayed to the learner when the "text" field is empty. For a picture discussion, '
                    . 'describe the picture here so the AI knows what the learner is looking at.',
                'example' => 'What did you do last weekend?',
            ],
            'audiochatrole' => [
                'description' => 'The character the AI assumes in the conversation, inserted where {ai role} '
                    . 'appears in the AI instructions.',
                'example' => 'A helpful language teacher',
            ],
            'audiochatinstructions' => [
                'description' => 'The instructions that drive the AI\'s side of the conversation: its role, '
                    . 'what to discuss, how simply to speak, and how/when to end the chat (e.g. "after 3 speaking '
                    . 'turns, thank them and ask them to press the end button"). ' . $placeholdernote,
                'example' => 'You are {ai role}. You are teaching {target language}. The student is a native '
                    . 'speaker of {native language}. Today the discussion topic is: {topic}. Speak simply and '
                    . 'slowly, keep your responses brief, and give the student every opportunity to speak.',
            ],
            'audiochatgradeinstructions' => [
                'required' => false,
                'description' => 'Instructions for grading the finished conversation and writing feedback: '
                    . 'the criteria for a score from 0-100, and the style/language of the feedback text. '
                    . 'When empty, the grade is calculated from the words spoken over the targetwordcount. '
                    . $placeholdernote,
                'example' => 'For the score consider: relevance to the topic "{topic}", fluency and vocabulary '
                    . 'usage. Feedback should be simple and in the student\'s native language: {native language}.',
            ],
            'audiochatvoice' => [
                'description' => 'The AI\'s speaking voice, inserted where {ai voice} appears in the AI '
                    . 'instructions (so the AI knows its own name).',
                'options' => [
                    ['value' => 'alloy', 'meaning' => 'Alloy (default)'],
                    ['value' => 'ash', 'meaning' => 'Ash'],
                    ['value' => 'ballad', 'meaning' => 'Ballad'],
                    ['value' => 'coral', 'meaning' => 'Coral'],
                    ['value' => 'echo', 'meaning' => 'Echo'],
                    ['value' => 'sage', 'meaning' => 'Sage'],
                    ['value' => 'shimmer', 'meaning' => 'Shimmer'],
                    ['value' => 'verse', 'meaning' => 'Verse'],
                    ['value' => 'marin', 'meaning' => 'Marin'],
                    ['value' => 'cedar', 'meaning' => 'Cedar'],
                ],
            ],
            'audiochatnativelanguage' => [
                'description' => 'The learner\'s native language, inserted where {native language} appears: '
                    . '"target" (the lesson language), "native" (the lesson\'s native language setting), '
                    . 'or a specific language code such as "ja-JP".',
                'example' => 'native',
            ],
            'audiochataidata1' => [
                'required' => false,
                'description' => 'Free text inserted where {ai data1} appears in the AI instructions, '
                    . 'e.g. a numbered list of questions the AI should ask one at a time.',
            ],
            'audiochataidata2' => [
                'required' => false,
                'description' => 'Free text inserted where {ai data2} appears in the AI instructions.',
            ],
            'studentsubmission' => [
                'description' => 'The id of another item in the same lesson (a freewriting or freespeaking item) '
                    . 'whose response is inserted where {student submission} appears - for follow-up discussion '
                    . 'of the learner\'s own answer. NOTE: item ids are site-specific, so when composing a new '
                    . 'lesson leave this at 0; it can be wired up afterwards in the item editing form.',
                'example' => '0',
            ],
            'totalmarks' => [
                'description' => 'The marks this item is graded out of. Set this explicitly (e.g. 10 or 20): '
                    . 'the import default is 0.',
                'example' => '10',
            ],
            'targetwordcount' => [
                'description' => 'Target number of words the learner should speak. Also the basis of the grade '
                    . 'when audiochatgradeinstructions is empty.',
                'example' => '30',
            ],
            'autoresponse' => [
                'description' => 'Whether the learner\'s audio is auto-submitted when silence is detected. '
                    . 'Many learners find auto-send difficult, so 0 is often the better choice.',
                'options' => [
                    ['value' => '1', 'meaning' => 'Auto-send on silence (default)'],
                    ['value' => '0', 'meaning' => 'The learner presses a button to send each turn'],
                ],
            ],
            'allowretry' => [
                'description' => 'Whether the learner can redo the conversation.',
                'options' => [
                    ['value' => '1', 'meaning' => 'Allow retries (default)'],
                    ['value' => '0', 'meaning' => 'One conversation only'],
                ],
            ],
            'audioavatar' => [
                'description' => 'The avatar image shown for the AI: "' . self::DEFAULT_AVATAR . '" (the default '
                    . 'poodle) or one of the presets "audiochatavatar1.jpg", "audiochatavatar2.jpg", '
                    . '"audiochatavatar3.png" .. "audiochatavatar12.png".',
                'example' => self::DEFAULT_AVATAR,
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
                . 'Only needed when the chat is about an uploaded picture.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per conversation. Everything the AI needs to know must be in '
                . 'audiochattopic, audiochatinstructions, audiochatgradeinstructions and the aidata fields - '
                . 'the AI cannot see the item name, instructions or text. Always tell the AI to speak simply, '
                . 'keep responses brief, and how to end the chat. For a picture discussion, upload the picture '
                . 'in the ' . constants::MEDIAQUESTION . ' file area, describe it in audiochattopic, and put '
                . 'the questions to ask in audiochataidata1.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::MEDIAQUESTION,
                    'description' => 'An uploaded image shown to the learner, e.g. the picture the chat is about. '
                        . 'The AI cannot see the image itself - describe it in audiochattopic.',
                    'filenames' => 'Any filename with an image extension (png/jpg/gif/svg).',
                ],
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'audiochat',
                        'name' => 'Audio chat: last weekend',
                        'instructions' => 'Practice speaking with your AI partner about the topic.',
                        'text' => 'What did you do last weekend?',
                        'audiochattopic' => 'What did you do last weekend?',
                        'audiochatrole' => 'A helpful language teacher',
                        'audiochatvoice' => 'sage',
                        'audiochatnativelanguage' => 'native',
                        'audiochatinstructions' => 'You are {ai role}. Your name is {ai voice}. You are teaching '
                            . '{target language}. The student is a native speaker of {native language}. Today the '
                            . 'discussion topic is: {topic}. Please discuss it with your student. Speak simply and '
                            . 'slowly. Your responses should be brief. Your aim is to give the student opportunity '
                            . 'to speak. After the student has had 3 speaking turns, thank them and ask them to '
                            . 'press the end button.',
                        'audiochatgradeinstructions' => 'For the score consider: relevance to the topic "{topic}", '
                            . 'fluency and vocabulary usage. Feedback should be simple, and in the student\'s '
                            . 'native language: {native language}.',
                        'totalmarks' => 10,
                        'targetwordcount' => 30,
                        'autoresponse' => 0,
                    ],
                ],
            ],
        ];
    }

    /*
     * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
     */
    public static function get_keycolumns() {
        // Get the basic key columns and customize a little for instances of this item type.
        $keycols = parent::get_keycolumns();
        $keycols['int1'] = [
            'jsonname' => 'totalmarks',
            'type' => 'int',
            'optional' => true,
            'default' => 0,
            'dbname' => constants::TOTALMARKS,
        ];
        $keycols['int2'] = [
            'jsonname' => 'relevance',
            'type' => 'int',
            'optional' => true,
            'default' => 0,
            'dbname' => constants::RELEVANCE,
        ];
        $keycols['int3'] = [
            'jsonname' => 'targetwordcount',
            'type' => 'int',
            'optional' => true,
            'default' => 0,
            'dbname' => constants::TARGETWORDCOUNT,
        ];
        $keycols['int4'] = [
            'jsonname' => 'autoresponse',
            'type' => 'int',
            'optional' => true,
            'default' => 1,
            'dbname' => self::AUTORESPONSE,
        ];
        $keycols['int5'] = [
            'jsonname' => 'allowretry',
            'type' => 'int',
            'optional' => true,
            'default' => 1,
            'dbname' => self::ALLOWRETRY,
        ];
        $keycols['int6'] = [
            'jsonname' => 'gradingselection',
            'type' => 'int',
            'optional' => true,
            'default' => 1,
            'dbname' => self::INSTRUCTIONSSELECTION,
        ];
        $keycols['int7'] = [
            'jsonname' => 'feedbackselection',
            'type' => 'int',
            'optional' => true,
            'default' => 1,
            'dbname' => self::FEEDBACKSELECTION,
        ];
        $keycols['text5'] = [
            'jsonname' => 'audiochattopic',
            'type' => 'string',
            'optional' => false,
            'default' => '',
            'dbname' => self::TOPIC,
        ];
        $keycols['text6'] = [
            'jsonname' => 'audiochatinstructions',
            'type' => 'string',
            'optional' => false,
            'default' => '',
            'dbname' => self::INSTRUCTIONS,
        ];
        $keycols['data3'] = [
            'jsonname' => 'audiochatgradeinstructions',
            'type' => 'string',
            'optional' => false,
            'default' => '',
            'dbname' => self::FEEDBACKINSTRUCTIONS,
        ];
        $keycols['data1'] = [
            'jsonname' => 'audiochataidata1',
            'type' => 'string',
            'optional' => false,
            'default' => '',
            'dbname' => self::AIDATA1,
        ];
        $keycols['data2'] = [
            'jsonname' => 'audiochataidata2',
            'type' => 'string',
            'optional' => false,
            'default' => '',
            'dbname' => self::AIDATA2,
        ];
        $keycols['text2'] = [
            'jsonname' => 'audiochatrole',
            'type' => 'string',
            'optional' => false,
            'default' => '',
            'dbname' => self::ROLE,
        ];
        $keycols['text3'] = [
            'jsonname' => 'audiochatvoice',
            'type' => 'string',
            'optional' => false,
            'default' => '',
            'dbname' => self::VOICE,
        ];
        $keycols['text4'] = [
            'jsonname' => 'audiochatnativelanguage',
            'type' => 'string',
            'optional' => true,
            'default' => 'en-US',
            'dbname' => self::NATIVE_LANGUAGE,
        ];
        $keycols['int8'] = [
            'jsonname' => 'studentsubmission',
            'type' => 'int',
            'optional' => true,
            'default' => 0,
            'dbname' => self::STUDENT_SUBMISSION,
        ];
        $keycols['text7'] = [
            'jsonname' => 'audioavatar',
            'type' => 'string',
            'optional' => true,
            'default' => '',
            'dbname' => self::AUDIOAVATAR,
        ];
        return $keycols;
    }

    /**
     * This function return the prompt that the generate method requires.
     */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Create an oral discussion topic(text) suitable for {level} level learners of {language} as a follow up activity on the following reading: [{text}] ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Generate an oral discussion topic(text) suitable for {level} level learners of {language} on the topic of: [{topic}] ";
                break;
        }
        return $prompt;
    }

    public function replace_student_submission($instruction) {
        if (empty($instruction)) {
            return false;
        }

        $studentsubmission = $this->fetch_student_submission();
        if ($studentsubmission && !empty($studentsubmission)) {
            $audiochatinstruction = str_replace(
                ['{student submission}'],
                [$studentsubmission],
                $instruction
            );
            return $audiochatinstruction;
        }
        return false;
    }

    public function fetch_student_submission() {
        $submission = $this->itemrecord;
        if (!empty($submission)) {
            $studentsubmissionitemid = $submission->{self::STUDENT_SUBMISSION};
            $attemptrec = utils::latest_attempt(
                $this->moduleinstance->course,
                $this->moduleinstance->id
            );
            $attemptrec = reset($attemptrec);
            $sessiondatas = json_decode($attemptrec->sessiondata);

            $studentsubmission = '';
            if (!empty($sessiondatas)) {
                foreach ($sessiondatas->steps as $sessiondata) {
                    if ($studentsubmissionitemid == $sessiondata->lessonitemid && !empty($sessiondata->resultsdata)) {
                        $studentsubmission = $sessiondata->resultsdata->rawspeech;
                        break;
                    }
                }
            }
            return $studentsubmission;
        }
        return false;
    }

    public function prepare_instructions_for_ai_grade(stdClass $instructions) {
        $search = ['{topic}', '{ai data1}', '{ai data2}', '{student submission}'];
        $item = $this->itemrecord;
        $studentsubmission = $this->fetch_student_submission();
        $replace = [
            $item->{self::TOPIC},
            $item->{self::AIDATA1},
            $item->{self::AIDATA2},
            $studentsubmission ? $studentsubmission : '',
        ];
        $instructions->feedbackscheme = str_replace($search, $replace, (string) $instructions->feedbackscheme);
        $instructions->markscheme = str_replace($search, $replace, (string) $instructions->markscheme);
    }

    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $search = ['{topic}', '{ai data1}', '{ai data2}'];
        $items = $this->itemrecord;
        $context = $this->context;
        $replace = [
            $items->{self::TOPIC},
            $items->{self::AIDATA1},
            $items->{self::AIDATA2},
        ];
        $itemtext = file_rewrite_pluginfile_urls(
            $items->{constants::TEXTQUESTION},
            'pluginfile.php',
            $context->id,
            constants::M_COMPONENT,
            constants::TEXTQUESTION_FILEAREA,
            $items->id
        );
        $itemtext = format_text($itemtext, FORMAT_MOODLE, ['context' => $context]);
        $result->questext = str_replace($search, $replace, $itemtext);
        $result->hascorrectanswer = false;
        $result->hasincorrectanswer = false;
        if (isset($result->resultsdata)) {
            $result->hasanswerdetails = true;
            $result->resultsdatajson = json_encode(
                $result->resultsdata,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } else {
            $result->hasanswerdetails = false;
        }
    }

    public static function is_configured() {
        return parent::is_configured();

        // Previously we required keys before it could be considered configured.
        // But now with CloudPoodll selected as provider it will work.

        /*
        if (!parent::is_configured()) {
            return false;
        }
        $config = get_config(constants::M_COMPONENT);
        return !empty($config->openaikey) || !empty($config->geminiapikey);
        */
    }
}
