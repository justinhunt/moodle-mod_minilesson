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
use templatable;
use renderable;

/**
 * Renderable class for a page item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_freespeaking extends item
{

    // The item type.
    public const ITEMTYPE = constants::TYPE_FREESPEAKING;

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

        $testitem = new \stdClass();
        $testitem = $this->get_common_elements($testitem);
        $testitem = $this->get_text_answer_elements($testitem);
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
        if ($isenglish) {
            $tokenobject = utils::fetch_streaming_token($this->moduleinstance->region);
            if ($tokenobject) {
                $testitem->speechtoken = $tokenobject->token;
                $testitem->speechtokenvalidseconds = $tokenobject->validseconds;
                $testitem->speechtokentype = 'assemblyai';
            } else {
                $testitem->speechtoken = false;
                $testitem->speechtokenvalidseconds = 0;
                $testitem->speechtokentype = '';
            }
            if ($alternatestreaming) {
                $testitem->forcestreaming = true;
            }
        }

        $testitem->reviewsettings['hidecorrections'] = !empty($this->itemrecord->{constants::FREESPEAKING_HIDECORRECTION});
        $testitem->reviewsettings['showreviewdetailed'] = empty($this->itemrecord->{constants::FREESPEAKING_SHOWRESULT}) ||
            $this->itemrecord->{constants::FREESPEAKING_SHOWRESULT} == 1;
        $testitem->reviewsettings['showreviewbasic'] = !empty($this->itemrecord->{constants::FREESPEAKING_SHOWRESULT}) &&
            $this->itemrecord->{constants::FREESPEAKING_SHOWRESULT} == 2;
        $testitem->reviewsettings['showscorestarrating'] = empty($this->itemrecord->{constants::FREESPEAKING_SHOWGRADE}) ||
            $this->itemrecord->{constants::FREESPEAKING_SHOWGRADE} == 1;
        $testitem->reviewsettings['showscorepercentage'] = !empty($this->itemrecord->{constants::FREESPEAKING_SHOWGRADE}) &&
            $this->itemrecord->{constants::FREESPEAKING_SHOWGRADE} == 2;

        // Replace any template variables in the question text.
        if(!empty($testitem->itemtext)){
            $search = ['{topic}', '{ai data1}', '{ai data2}'];
            $replace = [
                $this->itemrecord->{constants::FREESPEAKING_TOPIC},
                $this->itemrecord->{constants::FREESPEAKING_AIDATA1},
                $this->itemrecord->{constants::FREESPEAKING_AIDATA2},
            ];
            $testitem->itemtext = str_replace($search, $replace, $testitem->itemtext);
        }

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
        $keycols['int4'] = ['jsonname' => 'gradingselection', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::FREESPEAKING_GRADINGSELECTION];
        $keycols['int5'] = ['jsonname' => 'feedbackselection', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::FREESPEAKING_FEEDBACKSELECTION];
        $keycols['int6'] = ['jsonname' => 'hidecorrections', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::FREESPEAKING_HIDECORRECTION];
        $keycols['int7'] = ['jsonname' => 'showgrade', 'type' => 'int', 'optional' => true, 'default' => 1, 'dbname' => constants::FREESPEAKING_SHOWGRADE];
        $keycols['int8'] = ['jsonname' => 'showresult', 'type' => 'int', 'optional' => true, 'default' => 1, 'dbname' => constants::FREESPEAKING_SHOWRESULT];
        $keycols['text6'] = ['jsonname' => 'aigradeinstructions', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::AIGRADE_INSTRUCTIONS];
        $keycols['text2'] = ['jsonname' => 'aigradefeedback', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::AIGRADE_FEEDBACK];
        $keycols['text3'] = ['jsonname' => 'modelanswer', 'type' => 'string', 'optional' => true, 'default' => '', 'dbname' => constants::AIGRADE_MODELANSWER];
        $keycols['text4'] = ['jsonname' => 'aigradefeedbacklanguage', 'type' => 'string', 'optional' => true, 'default' => 'en-US', 'dbname' => constants::AIGRADE_FEEDBACK_LANGUAGE];
        $keycols['text5'] = ['jsonname' => 'freespeakingtopic', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::FREESPEAKING_TOPIC];
        $keycols['data1'] = ['jsonname' => 'freespeakingaidata1', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::FREESPEAKING_AIDATA1];
        $keycols['data2'] = ['jsonname' => 'freespeakingaidata2', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::FREESPEAKING_AIDATA2];
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

}
