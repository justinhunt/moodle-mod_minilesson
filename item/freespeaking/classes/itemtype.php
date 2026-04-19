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
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a page item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item
{
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

    // The item type.
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

        //add a few things to enable the saving of uploaded audio (on S3)
        $testitem->savemedia = 1;
        $testitem->transcode = 1;
        $testitem->expiredays = 365;
        $testitem->savemediaregion = $this->moduleinstance->region;

        // Cloudpoodll.
        $maxtime = $this->itemrecord->timelimit;
        $testitem = $this->set_cloudpoodll_details($testitem, $maxtime);

        return $testitem;
    }

    public static function validate_import($newrecord, $cm)
    {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if ($newrecord->{constants::AIGRADE_INSTRUCTIONS} == '') {
            $error->col = constants::AIGRADE_INSTRUCTIONS;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        if ($newrecord->{constants::AIGRADE_FEEDBACK} == '') {
            $error->col = constants::AIGRADE_FEEDBACK;
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
        $keycols['int4'] = ['jsonname' => 'gradingselection', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => self::GRADINGSELECTION];
        $keycols['int5'] = ['jsonname' => 'feedbackselection', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => self::FEEDBACKSELECTION];
        $keycols['int6'] = ['jsonname' => 'hidecorrections', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => self::HIDECORRECTION];
        $keycols['int7'] = ['jsonname' => 'showgrade', 'type' => 'int', 'optional' => true, 'default' => 1, 'dbname' => self::SHOWGRADE];
        $keycols['int8'] = ['jsonname' => 'showresult', 'type' => 'int', 'optional' => true, 'default' => 1, 'dbname' => self::SHOWRESULT];
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
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod)
    {
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
        if (isset($result->resultsdata)) {
            $result->hasanswerdetails = true;
            // The free writing and reading both need to be told to show no reattempt button.
            $result->resultsdata->noreattempt = true;
            $result->resultsdatajson = json_encode(
                $result->resultsdata,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } else {
            $result->hasanswerdetails = false;
        }
    }

}
