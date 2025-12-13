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

use mod_minilesson\constants;
use mod_minilesson\utils;
use moodle_url;

/**
 * Renderable class for an audiochat item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_audiochat extends item
{

    // The item type.
    public const ITEMTYPE = constants::TYPE_AUDIOCHAT;

    /** Default image avatar */
    public const DEFAULT_AVATAR = 'cutepoodll_small.png';

    /**
     * The class constructor.
     *
     */
    public function __construct($itemrecord, $moduleinstance = false, $context = false)
    {
        parent::__construct($itemrecord, $moduleinstance, $context);
        $this->needs_speechrec = true;
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output)
    {

        $testitem = parent::export_for_template($output);
        $testitem = $this->set_layout($testitem);

        // Do we have an OpenAI key? (we need one).
        $apikey = get_config(constants::M_COMPONENT, 'openaikey');
        if (empty($apikey)) {
            $testitem->canchat = false;
        } else {
            $testitem->canchat = true;
        }

        //Allow retry
        $testitem->allowretry = $this->itemrecord->{constants::AUDIOCHAT_ALLOWRETRY} == 1;

        // Replace the placeholders with what we know, first correcting missing placeholder data
        if (empty($this->itemrecord->{constants::AUDIOCHAT_ROLE})) {
            $this->itemrecord->{constants::AUDIOCHAT_ROLE} = get_string('audiochat_role_default', constants::M_COMPONENT);
        }
        if (empty($this->itemrecord->{constants::AUDIOCHAT_NATIVE_LANGUAGE})) {
            $this->itemrecord->{constants::AUDIOCHAT_NATIVE_LANGUAGE} = constants::M_LANG_ENUS;
        }
        if (empty($this->itemrecord->{constants::AUDIOCHAT_TOPIC})) {
            $this->itemrecord->{constants::AUDIOCHAT_TOPIC} = 'student choice of topic';
        }

        // Students native language - it is possible to use the one set in wordcards here also, so we check for that
        $testitem->audiochatnativelanguage = $this->itemrecord->{constants::AUDIOCHAT_NATIVE_LANGUAGE};
        if (get_config(constants::M_COMPONENT, 'setnativelanguage')) {
            $userprefdeflanguage = get_user_preferences('wordcards_deflang');
            if (!empty($userprefdeflanguage)) {
                $testitem->audiochatnativelanguage = $userprefdeflanguage;
            }
        }

        // Set up the audiochat instructions
        $testitem->audiochatinstructions = $this->itemrecord->{constants::AUDIOCHAT_INSTRUCTIONS};
        // If no topic was set, then we use the default topic.
        if (empty($testitem->audiochatinstructions)) {
            $testitem->audiochatinstructions = get_string('audiochat:gradingprompt_dec1', constants::M_COMPONENT);
        }

        // Replace the placeholders in the audiochat instructions with the actual data
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
                $this->itemrecord->{constants::AUDIOCHAT_ROLE},
                $this->itemrecord->{constants::AUDIOCHAT_VOICE},
                $testitem->audiochatnativelanguage,
                $this->language,
                $this->itemrecord->{constants::AUDIOCHAT_TOPIC},
                $this->itemrecord->{constants::AUDIOCHAT_AIDATA1},
                $this->itemrecord->{constants::AUDIOCHAT_AIDATA2},
            ],
            $testitem->audiochatinstructions
        );

        // Set up the audiochat grade instructions.
        $testitem->audiochatgradeinstructions = $this->itemrecord->{constants::AUDIOCHAT_FEEDBACKINSTRUCTIONS};
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
                    $this->itemrecord->{constants::AUDIOCHAT_ROLE},
                    $this->itemrecord->{constants::AUDIOCHAT_VOICE},
                    $testitem->audiochatnativelanguage,
                    $this->language,
                    $this->itemrecord->{constants::AUDIOCHAT_TOPIC},
                    $this->itemrecord->{constants::AUDIOCHAT_AIDATA1},
                    $this->itemrecord->{constants::AUDIOCHAT_AIDATA2},
                ],
                $testitem->audiochatgradeinstructions
            );
        }

        // Set the Auto turn detection to on or off.
        $testitem->audiochat_autoresponse = $this->itemrecord->{constants::AUDIOCHAT_AUTORESPONSE} ? true : false;

        // AI Voice.
        $testitem->audiochat_voice = $this->itemrecord->{constants::AUDIOCHAT_VOICE};

        $testitem->totalmarks = $this->itemrecord->{constants::TOTALMARKS};
        if ($this->itemrecord->{constants::TARGETWORDCOUNT} > 0) {
            $testitem->targetwordcount = $this->itemrecord->{constants::TARGETWORDCOUNT};
            $testitem->countwords = true;
        } else {
            $testitem->countwords = false;
        }

        // Replace any template variables in the question text.
        if(!empty($testitem->itemtext)){
            $search = ['{topic}', '{ai data1}', '{ai data2}'];
            $replace = [
                $this->itemrecord->{constants::AUDIOCHAT_TOPIC},
                $this->itemrecord->{constants::AUDIOCHAT_AIDATA1},
                $this->itemrecord->{constants::AUDIOCHAT_AIDATA2},
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

        $imgaudioavatar = $this->itemrecord->{constants::AUDIOCHAT_AUDIOAVATAR} ?
            $this->itemrecord->{constants::AUDIOCHAT_AUDIOAVATAR} :
            self::DEFAULT_AVATAR;
        $avatarimage = new moodle_url("/mod/minilesson/pix/{$imgaudioavatar}");
        $testitem->avatarimage = $avatarimage->out(false);

        return $testitem;
    }

    public static function validate_import($newrecord, $cm)
    {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if ($newrecord->{constants::AUDIOCHAT_INSTRUCTIONS} == '') {
            $error->col = constants::AUDIOCHAT_INSTRUCTIONS;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        if ($newrecord->{constants::AUDIOCHAT_ROLE} == '') {
            $error->col = constants::AUDIOCHAT_ROLE;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        // return false to indicate no error
        return false;
    }

    /*
     * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
     */
    public static function get_keycolumns()
    {
        // get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['int1'] = ['jsonname' => 'totalmarks', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::TOTALMARKS];
        $keycols['int2'] = ['jsonname' => 'relevance', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::RELEVANCE];
        $keycols['int3'] = ['jsonname' => 'targetwordcount', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::TARGETWORDCOUNT];
        $keycols['int4'] = ['jsonname' => 'autoresponse', 'type' => 'int', 'optional' => true, 'default' => 1, 'dbname' => constants::AUDIOCHAT_AUTORESPONSE];
        $keycols['int5'] = ['jsonname' => 'allowretry', 'type' => 'int', 'optional' => true, 'default' => 1, 'dbname' => constants::AUDIOCHAT_ALLOWRETRY];
        $keycols['int6'] = ['jsonname' => 'gradingselection', 'type' => 'int', 'optional' => true, 'default' => 1, 'dbname' => constants::AUDIOCHAT_INSTRUCTIONSSELECTION];
        $keycols['int7'] = ['jsonname' => 'feedbackselection', 'type' => 'int', 'optional' => true, 'default' => 1, 'dbname' => constants::AUDIOCHAT_FEEDBACKSELECTION];
        $keycols['text5'] = ['jsonname' => 'audiochattopic', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::AUDIOCHAT_TOPIC];
        $keycols['text6'] = ['jsonname' => 'audiochatinstructions', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::AUDIOCHAT_INSTRUCTIONS];
        $keycols['data3'] = ['jsonname' => 'audiochatgradeinstructions', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::AUDIOCHAT_FEEDBACKINSTRUCTIONS];
        $keycols['data1'] = ['jsonname' => 'audiochataidata1', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::AUDIOCHAT_AIDATA1];
        $keycols['data2'] = ['jsonname' => 'audiochataidata2', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::AUDIOCHAT_AIDATA2];
        $keycols['text2'] = ['jsonname' => 'audiochatrole', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::AUDIOCHAT_ROLE];
        $keycols['text3'] = ['jsonname' => 'audiochatvoice', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::AUDIOCHAT_VOICE];
        $keycols['text4'] = ['jsonname' => 'audiochatnativelanguage', 'type' => 'string', 'optional' => true, 'default' => 'en-US', 'dbname' => constants::AUDIOCHAT_NATIVE_LANGUAGE];
        $keycols['int8'] = ['jsonname' => 'studentsubmission', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::AUDIOCHAT_STUDENT_SUBMISSION];
        $keycols['text7'] = ['jsonname' => 'audioavatar', 'type' => 'string', 'optional' => true, 'default' => '', 'dbname' => constants::AUDIOCHAT_AUDIOAVATAR];
        return $keycols;
    }

    /*
  This function return the prompt that the generate method requires.
  */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod)
    {
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
            $studentsubmissionitemid = $submission->{constants::AUDIOCHAT_STUDENT_SUBMISSION};
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

}
