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

namespace minilessonitem_freespeaking;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use mod_minilesson\local\cpage;
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
    public static $skills = [constants::SKILL_SPEAKING];

    /**
     * A free speaking response can be given to later items as context.
     *
     * @return bool
     */
    public static function produces_student_text() {
        return true;
    }

    /**
     * This item type has a splash screen, drawn as a centred white card.
     *
     * @return bool
     */
    public function uses_boxed_layout() {
        return true;
    }

    public const TOPIC = 'customtext5';
    public const AIDATA1 = 'customdata1';
    public const AIDATA2 = 'customdata2';
    public const GRADINGINSTRUCTIONS = 'customtext6';
    public const FEEDBACKINSTRUCTIONS = 'customtext2';
    public const GRADINGSELECTION = 'customint4';
    public const FEEDBACKSELECTION = 'customint5';
    public const HIDECORRECTION = 'customint6';
    public const SHOWGRADE = 'customint7';
    public const SHOWRESULT = 'customint8';
    public const COMMUNITYPAGE = 'customint9';
    public const COMMUNITYLIKES = 'customint10';

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
        $testitem->relevance = $this->itemrecord->{constants::RELEVANCE};
        $testitem->totalmarks = $this->itemrecord->{constants::TOTALMARKS};
        if ($this->itemrecord->{constants::TARGETWORDCOUNT} > 0) {
            $testitem->targetwordcount = $this->itemrecord->{constants::TARGETWORDCOUNT};
            $testitem->countwords = true;
        } else {
            $testitem->countwords = false;
        }

        // We need cmid and itemid to do the AI evaluation by ajax.
        $testitem->itemid = $this->itemrecord->id;

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

        $testitem->reviewsettings['hidecorrections'] = !empty($this->itemrecord->{self::HIDECORRECTION});
        $testitem->reviewsettings['showreviewdetailed'] = empty($this->itemrecord->{self::SHOWRESULT}) ||
            $this->itemrecord->{self::SHOWRESULT} == 1;
        $testitem->reviewsettings['showreviewbasic'] = !empty($this->itemrecord->{self::SHOWRESULT}) &&
            $this->itemrecord->{self::SHOWRESULT} == 2;
        $testitem->reviewsettings['showscorestarrating'] = empty($this->itemrecord->{self::SHOWGRADE}) ||
            $this->itemrecord->{self::SHOWGRADE} == 1;
        $testitem->reviewsettings['showscorepercentage'] = !empty($this->itemrecord->{self::SHOWGRADE}) &&
            $this->itemrecord->{self::SHOWGRADE} == 2;

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

        // add a few things to enable the saving of uploaded audio (on S3)
        $testitem->savemedia = 1;
        $testitem->transcode = 1;
        $testitem->expiredays = 365;
        $testitem->savemediaregion = $this->moduleinstance->region;

        // Community page.
        $testitem->communitypage = $this->community_page_enabled();
        $testitem->communitylikes = $this->community_likes_enabled();
        if ($testitem->communitypage) {
            global $USER, $PAGE;
            $submission = cpage::get_submission($this->itemrecord->id, $USER->id);
            $testitem->cpageconsent = $submission && !empty($submission->consent);
            $testitem->cpagethreshold = $this->community_eligibility_grade();
            // The user's own display data, so their fresh submission can be
            // shown on the community page before it is saved server side.
            $userpicture = new \user_picture($USER);
            $userpicture->size = 64;
            $testitem->myfullname = fullname($USER);
            $testitem->myprofileimageurl = $userpicture->get_url($PAGE)->out(false);
            $countries = get_string_manager()->get_list_of_countries(true);
            $testitem->mycountry = isset($countries[$USER->country]) ? $countries[$USER->country] : '';
        }

        // Cloudpoodll.
        $maxtime = $this->itemrecord->timelimit;
        $testitem = $this->set_cloudpoodll_details($testitem, $maxtime);

        return $testitem;
    }

    /**
     * Is the community page on for this item (site setting AND item setting)?
     *
     * @return bool
     */
    public function community_page_enabled() {
        return cpage::is_enabled_sitewide() && !empty($this->itemrecord->{self::COMMUNITYPAGE});
    }

    /**
     * Are likes allowed on this item's community page?
     *
     * @return bool
     */
    public function community_likes_enabled() {
        return $this->community_page_enabled() && !empty($this->itemrecord->{self::COMMUNITYLIKES});
    }

    /**
     * The minimum step grade (percent) for a submission to be eligible.
     *
     * The COMMUNITYPAGE column stores the threshold itself: 0 = disabled,
     * otherwise the minimum grade. A legacy value of 1 (from when the column
     * was a checkbox) means the default threshold.
     *
     * @return int
     */
    public function community_eligibility_grade() {
        $value = (int) $this->itemrecord->{self::COMMUNITYPAGE};
        return $value > 1 ? $value : cpage::ELIGIBLE_GRADE;
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

        if (trim((string) $newrecord->{constants::AIGRADE_INSTRUCTIONS}) == '') {
            $error->col = constants::AIGRADE_INSTRUCTIONS;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        if (trim((string) $newrecord->{constants::AIGRADE_FEEDBACK}) == '') {
            $error->col = constants::AIGRADE_FEEDBACK;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        // Option value checks: reject impossible values. Absent JSON fields and blank CSV cells arrive here as 0,
        // and the runtime treats 0 as the default for showgrade/showresult (see export_for_template), so 0 is allowed.
        $optionchecks = [
            constants::RELEVANCE => [constants::RELEVANCETYPE_NONE, constants::RELEVANCETYPE_QUESTION,
                constants::RELEVANCETYPE_MODELANSWER],
            self::SHOWGRADE => [0, 1, 2],
            self::SHOWRESULT => [0, 1, 2],
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

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'An open speaking task graded by AI. The learner records a spoken answer; it is transcribed and '
            . 'the AI marks the transcript against your grading instructions and writes feedback. Use it for '
            . 'discussion questions, opinion prompts, picture descriptions, or any speaking task where the answer '
            . 'wording cannot be predicted. For questions with predictable short spoken answers use shortanswer '
            . 'instead. Requires a lesson language with speech recognition support.';
    }

    /**
     * The agent-facing import field spec for freespeaking. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions', 'text',
            'tts', 'ttsvoice', 'ttsoption', 'ttsautoplay', 'timelimit', 'layout']);
        $fields['type']['example'] = 'freespeaking';
        $fields['text']['description'] = 'The speaking question or prompt shown to the learner. May contain the '
            . 'placeholders {topic}, {ai data1} and {ai data2}, which are filled from freespeakingtopic, '
            . 'freespeakingaidata1 and freespeakingaidata2.';
        $fields['timelimit']['description'] = 'Maximum recording time in seconds. 0 = no limit.';

        $ownfields = [
            'aigradeinstructions' => [
                'description' => 'The mark scheme the AI grader applies to the transcript of the spoken answer. '
                    . 'Write it so points are assigned from 0 (or deducted from the maximum) such that a great '
                    . 'answer scores exactly totalmarks, e.g. "Deduct 3 points for each grammar mistake." '
                    . 'May contain the {topic}, {ai data1} and {ai data2} placeholders.',
                'example' => 'Deduct 3 points for each grammar mistake. Do not penalize for spelling or punctuation errors.',
            ],
            'aigradefeedback' => [
                'description' => 'Instructions the AI follows when writing feedback to the learner, '
                    . 'e.g. "Explain each grammar mistake in simple language." '
                    . 'May contain the {topic}, {ai data1} and {ai data2} placeholders.',
                'example' => 'Explain each grammar mistake in simple language.',
            ],
            'aigradefeedbacklanguage' => [
                'description' => 'The language the AI writes its feedback in: "target" (the lesson language), '
                    . '"native" (the learner\'s native language, when the lesson has one set), '
                    . 'or a specific language code such as "en-US".',
                'example' => 'native',
            ],
            'totalmarks' => [
                'description' => 'The marks this item is graded out of. Set this explicitly (e.g. 10 or 20): '
                    . 'the import default is 0 but the authoring form uses 5.',
                'example' => '20',
            ],
            'targetwordcount' => [
                'description' => 'Target length of the spoken answer in words. Scales the grade down for short '
                    . 'answers: the score is multiplied by (words spoken / targetwordcount), capped at 100%. '
                    . '0 disables the word count factor.',
                'example' => '30',
            ],
            'relevance' => [
                'description' => 'Whether an AI relevance check is applied. The relevance score multiplies the '
                    . 'grade as a percentage; minor irrelevance is not harshly penalised.',
                'options' => [
                    ['value' => (string) constants::RELEVANCETYPE_NONE,
                        'meaning' => 'Do not check relevance (default)'],
                    ['value' => (string) constants::RELEVANCETYPE_QUESTION,
                        'meaning' => 'The answer must be relevant to the question text'],
                    ['value' => (string) constants::RELEVANCETYPE_MODELANSWER,
                        'meaning' => 'The answer must be relevant to the modelanswer text'],
                ],
            ],
            'modelanswer' => [
                'description' => 'A model answer used for the relevance check when relevance=2.',
            ],
            'hidecorrections' => [
                'description' => 'Whether the AI\'s corrections are hidden in the results screen '
                    . '(useful when it is not a language learning course).',
                'options' => [
                    ['value' => '0', 'meaning' => 'Show corrections (default)'],
                    ['value' => '1', 'meaning' => 'Hide corrections'],
                ],
            ],
            'showgrade' => [
                'description' => 'How the grade is displayed to the learner.',
                'options' => [
                    ['value' => '1', 'meaning' => 'Star rating (default)'],
                    ['value' => '2', 'meaning' => 'Percentage score'],
                ],
            ],
            'showresult' => [
                'description' => 'How much result detail the learner sees.',
                'options' => [
                    ['value' => '1', 'meaning' => 'Detailed results (default)'],
                    ['value' => '2', 'meaning' => 'Basic result only'],
                ],
            ],
            'freespeakingtopic' => [
                'required' => false,
                'description' => 'Context text inserted wherever {topic} appears in the question text and '
                    . 'AI grading/feedback instructions.',
            ],
            'freespeakingaidata1' => [
                'required' => false,
                'description' => 'Context text inserted wherever {ai data1} appears in the question text and '
                    . 'AI grading/feedback instructions.',
            ],
            'freespeakingaidata2' => [
                'required' => false,
                'description' => 'Context text inserted wherever {ai data2} appears in the question text and '
                    . 'AI grading/feedback instructions.',
            ],
            'communitypage' => [
                'description' => 'Class community page sharing: 0 = disabled, otherwise the minimum grade percent '
                    . '(40-90) a submission needs to be shareable. Only takes effect when the site has the '
                    . 'community page feature enabled.',
            ],
            'communitylikes' => [
                'description' => 'Set to 1 to allow likes on shared community page submissions.',
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
                . 'Only needed when the item has an uploaded media prompt (e.g. a picture to describe).',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per speaking task. Put the speaking prompt in "text", the AI mark '
                . 'scheme in "aigradeinstructions" and the AI feedback style in "aigradefeedback" (both required), '
                . 'and set totalmarks. The grade combines three factors: the AI score (out of totalmarks), '
                . 'a word count factor (words spoken / targetwordcount, when set) and a relevance percentage '
                . '(when relevance is enabled). AI grades are indicative rather than authoritative assessments. '
                . 'A picture description task can supply the picture in the '
                . constants::MEDIAQUESTION . ' file area.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::MEDIAQUESTION,
                    'description' => 'An uploaded image, audio or video shown as the prompt, '
                        . 'e.g. a picture for the learner to describe.',
                    'filenames' => 'Any filename; the extension decides how it renders: '
                        . 'image (png/jpg/gif/svg), video (mp4/mov/webm) or audio (mp3/m4a/ogg/wav).',
                ],
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'freespeaking',
                        'name' => 'Free speaking',
                        'instructions' => 'Use the microphone to record your answer to the question.',
                        'text' => 'Have you ever seen someone cheat in a game or race? '
                            . 'What do you think about someone who cheats to win?',
                        'aigradeinstructions' => 'Deduct 3 points for each grammar mistake. '
                            . 'Do not penalize for spelling or punctuation errors.',
                        'aigradefeedback' => 'Explain each grammar mistake in simple language.',
                        'totalmarks' => 20,
                        'targetwordcount' => 30,
                        'relevance' => constants::RELEVANCETYPE_QUESTION,
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
        $keycols['int2'] = ['jsonname' => 'relevance', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::RELEVANCE];
        $keycols['int3'] = ['jsonname' => 'targetwordcount', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::TARGETWORDCOUNT];
        $keycols['int4'] = ['jsonname' => 'gradingselection', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => self::GRADINGSELECTION];
        $keycols['int5'] = ['jsonname' => 'feedbackselection', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => self::FEEDBACKSELECTION];
        $keycols['int6'] = ['jsonname' => 'hidecorrections', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => self::HIDECORRECTION];
        $keycols['int7'] = ['jsonname' => 'showgrade', 'type' => 'int', 'optional' => true, 'default' => 1, 'dbname' => self::SHOWGRADE];
        $keycols['int8'] = ['jsonname' => 'showresult', 'type' => 'int', 'optional' => true, 'default' => 1, 'dbname' => self::SHOWRESULT];
        $keycols['int9'] = ['jsonname' => 'communitypage', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => self::COMMUNITYPAGE];
        $keycols['int10'] = ['jsonname' => 'communitylikes', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => self::COMMUNITYLIKES];
        $keycols['text6'] = ['jsonname' => 'aigradeinstructions', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::AIGRADE_INSTRUCTIONS];
        $keycols['text2'] = ['jsonname' => 'aigradefeedback', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::AIGRADE_FEEDBACK];
        $keycols['text3'] = ['jsonname' => 'modelanswer', 'type' => 'string', 'optional' => true, 'default' => '', 'dbname' => constants::AIGRADE_MODELANSWER];
        $keycols['text4'] = ['jsonname' => 'aigradefeedbacklanguage', 'type' => 'string', 'optional' => true, 'default' => 'en-US', 'dbname' => constants::AIGRADE_FEEDBACK_LANGUAGE];
        $keycols['text5'] = ['jsonname' => 'freespeakingtopic', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => self::TOPIC];
        $keycols['data1'] = ['jsonname' => 'freespeakingaidata1', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => self::AIDATA1];
        $keycols['data2'] = ['jsonname' => 'freespeakingaidata2', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => self::AIDATA2];
        return $keycols;
    }

    /*
    This function return the prompt that the generate method requires.
    */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Create an oral discussion question(text) suitable for {level} level learners of {language} as a follow up activity on the following reading: [{text}] ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Generate an oral discussion question(text) suitable for {level} level learners of {language} on the topic of: [{topic}] ";
                break;
        }
        return $prompt;
    }

    public function prepare_instructions_for_ai_grade(stdClass $instructions) {
        $search = ['{topic}', '{ai data1}', '{ai data2}'];
        $item = $this->itemrecord;
        $replace = [
            $item->{self::TOPIC},
            $item->{self::AIDATA1},
            $item->{self::AIDATA2},
        ];
        $instructions->feedbackscheme = str_replace($search, $replace, (string) $instructions->feedbackscheme);
        $instructions->markscheme = str_replace($search, $replace, (string) $instructions->markscheme);
    }

    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $items = $this->itemrecord;
        $search = ['{topic}', '{ai data1}', '{ai data2}'];
        $replace = [
            $items->{self::TOPIC},
            $items->{self::AIDATA1},
            $items->{self::AIDATA2},
        ];
        $result->questext = str_replace($search, $replace, $result->questext);
        $result->hascorrectanswer = false;
        $result->hasincorrectanswer = false;
        // Community page button on the item's results header on the quiz finished page.
        if ($this->community_page_enabled()) {
            $result->hascommunitypage = true;
            $result->cpageitemid = $items->id;
        }
        if (isset($result->resultsdata)) {
            $result->hasanswerdetails = true;
            // The free writing and reading both need to be told to show no reattempt button.
            $result->resultsdata->noreattempt = true;
            // Community page consent toggle on the quiz finished page.
            if ($this->community_page_enabled()) {
                global $USER;
                $cpagedata = new stdClass();
                $cpagedata->itemid = $items->id;
                $cpagedata->canshare = cpage::can_share(
                    $items,
                    $this->moduleinstance,
                    $USER->id,
                    $this->community_eligibility_grade()
                );
                $submission = cpage::get_submission($items->id, $USER->id);
                $cpagedata->consent = $submission && !empty($submission->consent);
                $result->resultsdata->cpage = $cpagedata;
            }
            $result->resultsdatajson = json_encode(
                $result->resultsdata,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } else {
            $result->hasanswerdetails = false;
        }
    }

}
