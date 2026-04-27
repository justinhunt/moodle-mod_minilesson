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
    public const CHAT_PROVIDER = 'customtext1';

    /** @var string */
    public const PROVIDER_GEMINI = 'gemini';

    /** @var string */
    public const PROVIDER_OPENAI = 'openai';

    /**
     * The class constructor.
     */
    public function __construct($itemrecord, $moduleinstance = false, $context = false) {
        parent::__construct($itemrecord, $moduleinstance, $context);
        $this->needs_speechrec = true;
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
        $provider = $testitem->{self::CHAT_PROVIDER};

        // Do we have an OpenAI key? (we need one).
        $testitem->canchat = false;
        $testitem->provider = get_string('openai', self::get_component());
        if ($provider == self::PROVIDER_OPENAI) {
            $apikey = get_config(constants::M_COMPONENT, 'openaikey');
            $testitem->canchat = !empty($apikey);
        } else if ($provider == self::PROVIDER_GEMINI) {
            $apikey = get_config(constants::M_COMPONENT, 'geminiapikey');
            $testitem->provider = get_string('gemini', self::get_component());
            $testitem->canchat = !empty($apikey);
        }

        $testitem->itisningxiaregion = $this->region == 'ningxia';

        // Allow retry.
        $testitem->allowretry = $this->itemrecord->{self::ALLOWRETRY} == 1;

        // Replace the placeholders with what we know, first correcting missing placeholder data.
        if (empty($this->itemrecord->{self::ROLE})) {
            $this->itemrecord->{self::ROLE} = get_string('audiochat_role_default', constants::M_COMPONENT);
        }
        if (empty($this->itemrecord->{self::NATIVE_LANGUAGE})) {
            $this->itemrecord->{self::NATIVE_LANGUAGE} = constants::M_LANG_ENUS;
        }
        if (empty($this->itemrecord->{self::TOPIC})) {
            $this->itemrecord->{self::TOPIC} = 'student choice of topic';
        }

        // Students native language - it is possible to use the one set in wordcards here also, so we check for that.
        $testitem->audiochatnativelanguage = $this->itemrecord->{self::NATIVE_LANGUAGE};
        if (get_config(constants::M_COMPONENT, 'setnativelanguage')) {
            $userprefnativelanguage = get_user_preferences(constants::NATIVELANG_PREF);
            if (!empty($userprefnativelanguage)) {
                $testitem->audiochatnativelanguage = $userprefnativelanguage;
            }
        }

        // Set up the audiochat instructions.
        $testitem->audiochatinstructions = $this->itemrecord->{self::INSTRUCTIONS};
        // If no topic was set, then we use the default topic.
        if (empty($testitem->audiochatinstructions)) {
            $testitem->audiochatinstructions = get_string('audiochat:gradingprompt_dec1', constants::M_COMPONENT);
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

        // We might need cmid and itemid to do the AI evaluation by ajax.
        $testitem->itemid = $this->itemrecord->id;
        // Not sure if we need this.
        $testitem->maxtime = $this->itemrecord->timelimit;

        // If we add a cloud poodll recorder to the page these are also added, but here we just add them manually.
        $testitem->language = $this->language;
        $testitem->region = $this->region;
        $testitem->chatprovider = $this->itemrecord->{self::CHAT_PROVIDER};

        $imgaudioavatar = $this->itemrecord->{self::AUDIOAVATAR} ?
            $this->itemrecord->{self::AUDIOAVATAR} :
            self::DEFAULT_AVATAR;
        $testitem->avatarimage = $output->image_url(
            pathinfo($imgaudioavatar, PATHINFO_FILENAME),
            self::get_component()
        )->out(false);

        return $testitem;
    }

    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if ($newrecord->{self::INSTRUCTIONS} == '') {
            $error->col = self::INSTRUCTIONS;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        if ($newrecord->{self::ROLE} == '') {
            $error->col = self::ROLE;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }
        return false;
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
            'dbname' => self::AUDIOAVATAR
        ];
        $keycols['data5'] = [
            'jsonname' => 'chatprovider',
            'type' => 'string',
            'optional' => false,
            'default' => '',
            'dbname' => self::CHAT_PROVIDER,
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
        if (!parent::is_configured()) {
            return false;
        }
        $config = get_config(constants::M_COMPONENT);
        return !empty($config->openaikey) || !empty($config->geminiapikey);
    }
}
