<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/06/26
 * Time: 13:16
 */

namespace mod_minilesson\output;

use html_writer;
use mod_minilesson\constants;
use mod_minilesson\utils;
use mod_minilesson\comprehensiontest;

class renderer extends \plugin_renderer_base {

    /**
     * Returns the header for the module
     *
     * @param mod $instance
     * @param string $currenttab current tab that is shown.
     * @param int    $item id of the anything that needs to be displayed.
     * @param string $extrapagetitle String to append to the page title.
     * @return string
     */
    public function header($moduleinstance, $cm, $currenttab = '', $itemid = null, $extrapagetitle = null) {
        global $CFG;

        $activityname = format_string($moduleinstance->name, true, $moduleinstance->course);
        if (empty($extrapagetitle)) {
            $title = $this->page->course->shortname.": ".$activityname;
        } else {
            $title = $this->page->course->shortname.": ".$activityname.": ".$extrapagetitle;
        }

        // Build the buttons
        $context = \context_module::instance($cm->id);

        /// Header setup
        $this->page->set_title($title);
        $this->page->set_heading($this->page->course->fullname);
        $output = $this->output->header();

        // show (or not) title
        $output .= $this->fetch_title($moduleinstance, $activityname);

        if (has_capability('mod/minilesson:evaluate', $context)) {
            // $output .= $this->output->heading_with_help($activityname, 'overview', constants::M_COMPONENT);

            if (!empty($currenttab)) {
                ob_start();
                include($CFG->dirroot.'/mod/minilesson/tabs.php');
                $output .= ob_get_contents();
                ob_end_clean();
            }
        }

        return $output;
    }

    public function fetch_title($moduleinstance, $title) {
        $displaytext = '';
        // dont show the heading in an iframe, it will be outside this anyway
        if(!$moduleinstance->foriframe && $moduleinstance->pagelayout !== 'embedded') {
            $thetitle = $this->output->heading($title, 3, 'main');
            $displaytext = \html_writer::div($thetitle, constants::M_CLASS . '_center');
        }
        return $displaytext;
    }

    /**
     * Returns the header for the module
     *
     * @param mod $instance
     * @param string $currenttab current tab that is shown.
     * @param int    $item id of the anything that needs to be displayed.
     * @param string $extrapagetitle String to append to the page title.
     * @return string
     */
    public function simpleheader($moduleinstance, $cm, $extrapagetitle = null) {
        global $CFG;

        $activityname = format_string($moduleinstance->name, true, $moduleinstance->course);
        if (empty($extrapagetitle)) {
            $title = $this->page->course->shortname.": ".$activityname;
        } else {
            $title = $this->page->course->shortname.": ".$activityname.": ".$extrapagetitle;
        }

        // Build the buttons
        $context = \context_module::instance($cm->id);

        /// Header setup
        $this->page->set_title($title);
        $this->page->set_heading($this->page->course->fullname);
        $output = $this->output->header();

        // show (or not) title
        $output .= $this->fetch_title($moduleinstance, $activityname);

        return $output;
    }

    /**
     * Return HTML to display limited header
     */
    public function notabsheader($moduleinstance) {

        $output = $this->output->header();

        // show (or not) title -  we dont need this do we?
        // $activityname = format_string($moduleinstance->name, true, $moduleinstance->course);
        // $output .= $this->fetch_title($moduleinstance, $activityname);
        return $output;
    }
    /**
     * Return a message that something is not right
     */
    public function thatsnotright($message) {

        $ret = $this->output->heading(get_string('thatsnotright', constants::M_COMPONENT), 3);
        $ret .= \html_writer::div($message, constants::M_CLASS  . '_thatsnotright_message');
        return $ret;
    }

    public function backtotopbutton($courseid) {

        $button = $this->output->single_button(new \moodle_url( '/course/view.php',
            ['id' => $courseid]), get_string('backtotop', constants::M_COMPONENT));

        $ret = \html_writer::div($button , constants::M_CLASS  . '_backtotop_cont');
        return $ret;
    }

    public function back_to_import_button($cm) {
        https:// vbox.poodll.com/moodle/mod/minilesson/import.php?id=2081
        $button = $this->output->single_button(new \moodle_url( constants::M_PATH . '/import.php',
            ['id' => $cm->id]), get_string('backtoimport', constants::M_COMPONENT));

        $ret = \html_writer::div($button , constants::M_CLASS  . '_backtoimport_cont');
        return $ret;
    }


    /**
     *
     */
    public function reattemptbutton($moduleinstance) {

        $button = $this->output->single_button(new \moodle_url(constants::M_URL . '/view.php',
            ['n' => $moduleinstance->id, 'retake' => 1]), get_string('reattempt', constants::M_COMPONENT));

        $ret = \html_writer::div($button , constants::M_CLASS  . '_afterattempt_cont');
        return $ret;

    }

    /**
     *
     */
    public function show_wheretonext($moduleinstance) {

        $nextactivity = utils::fetch_next_activity($moduleinstance->activitylink);
        // show activity link if we are up to it
        if ($nextactivity->url) {
            $button = $this->output->single_button($nextactivity->url, $nextactivity->label);
            // else lets show a back to top link
        }else {
            $button = $this->output->single_button(new \moodle_url(constants::M_URL . '/view.php',
                ['n' => $moduleinstance->id]), get_string('backtotop', constants::M_COMPONENT));
        }
        $ret = \html_writer::div($button , constants::M_WHERETONEXT_CONTAINER);
        return $ret;

    }



    /**
     *
     */
    public function exceededattempts($moduleinstance) {
        $message = get_string("exceededattempts", constants::M_COMPONENT, $moduleinstance->maxattempts);
        $ret = \html_writer::div($message , constants::M_CLASS  . '_afterattempt_cont');
        return $ret;

    }



    /**
     *  Show instructions/welcome
     */
    public function show_welcome($showtext, $showtitle) {
        $thetitle = $this->output->heading($showtitle, 3, 'main');
        $displaytext = \html_writer::div($thetitle , constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_start();
        $displaytext .= \html_writer::div($showtext , constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_end();
        $ret = \html_writer::div($displaytext, constants::M_INSTRUCTIONS_CONTAINER, ['id' => constants::M_INSTRUCTIONS_CONTAINER]);
        return $ret;
    }

    /**
     * Show the introduction text is as set in the activity description
     */
    public function show_intro($minilesson, $cm) {
        $ret = "";
        if (trim(strip_tags($minilesson->intro))) {
            $ret .= $this->output->box_start('mod_introbox');
            $ret .= format_module_intro('minilesson', $minilesson, $cm->id);
            $ret .= $this->output->box_end();
        }
        return $ret;
    }

    /**
     * Show error (but when?)
     */
    public function show_no_items($cm, $showadditemlinks) {
        $displaytext = $this->output->box_start();
        $displaytext .= $this->output->heading(get_string('noitems', constants::M_COMPONENT), 3, 'main');
        if ($showadditemlinks) {
            $displaytext .= \html_writer::div(get_string('letsadditems', constants::M_COMPONENT), '', []);
            $displaytext .= $this->output->single_button(new \moodle_url(constants::M_URL . '/rsquestion/rsquestions.php',
                ['id' => $cm->id]), get_string('additems', constants::M_COMPONENT));
        }
        $displaytext .= $this->output->box_end();
        $ret = \html_writer::div($displaytext, constants::M_NOITEMS_MSG, ['id' => constants::M_NOITEMS_MSG]);
        return $ret;
    }

    /**
     *  Finished View
     */
    public function show_finished_results($comptest, $latestattempt, $cm, $canattempt, $embed, $teacherreport = false) {
        global $CFG, $DB;
        $ans = [];
        // quiz data
        $quizdata = $comptest->fetch_test_data_for_js();

        // config
        $config = get_config(constants::M_COMPONENT);
        $course = $DB->get_record('course', ['id' => $latestattempt->courseid]);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // steps data
        $steps = json_decode($latestattempt->sessiondata)->steps;

        // prepare results for display
        if(!is_array($steps)){$steps = utils::remake_steps_as_array($steps);
        }
        $results = array_filter($steps, function($step){return $step->hasgrade;
        });
        $useresults = [];
        foreach($results as $result){
            /*
            if(isset($quizdata[$result->index]->title)) {
                $result->title = $quizdata[$result->index]->title;
            }else{
                $result->title = get_string($quizdata[$result->index]->type,constants::M_COMPONENT);
            }
            */

            $items = $DB->get_record(constants::M_QTABLE, ['id' => $quizdata[$result->index]->id]);
            $result->title = $items->name;

            // Question Text
            $itemtext = file_rewrite_pluginfile_urls($items->{constants::TEXTQUESTION},
                'pluginfile.php', $context->id, constants::M_COMPONENT,
                constants::TEXTQUESTION_FILEAREA, $items->id);
            $itemtext = format_text($itemtext, FORMAT_MOODLE, ['context' => $context]);
            $result->questext = $itemtext;
            $result->itemtype = $quizdata[$result->index]->type;
            $result->resultstemplate =  $result->itemtype .'results';

            // Correct answer.
            switch($result->itemtype){
                case constants::TYPE_DICTATION:
                case constants::TYPE_DICTATIONCHAT:
                case constants::TYPE_LISTENREPEAT:
                case constants::TYPE_SPEECHCARDS:
                case constants::TYPE_SHORTANSWER:
                case constants::TYPE_LGAPFILL:
                case constants::TYPE_TGAPFILL:
                case constants::TYPE_SGAPFILL:
                case constants::TYPE_FLUENCY:
                    $result->hascorrectanswer = true;
                    $result->correctans = $quizdata[$result->index]->sentences;
                    $result->hasanswerdetails = false;
                    break;

                case constants::TYPE_MULTIAUDIO:
                case constants::TYPE_MULTICHOICE:
                case constants::TYPE_COMPQUIZ:
                case constants::TYPE_BUTTONQUIZ:
                    $result->hascorrectanswer = true;
                    $result->hasincorrectanswer = true;
                    $result->hasanswerdetails = false;
                    $correctanswers = [];
                    $incorrectanswers = [];
                    $correctindex = $quizdata[$result->index]->correctanswer;
                    for($i = 1; $i < 5; $i++){
                        if(!isset($quizdata[$result->index]->{"customtext" . $i})){continue;
                        }
                        if($i == $correctindex){
                            $correctanswers[] = ['sentence' => $quizdata[$result->index]->{"customtext" . $i}];
                        }else{
                            $incorrectanswers[] = ['sentence' => $quizdata[$result->index]->{"customtext" . $i}];
                        }
                    }
                    $result->correctans = $correctanswers;
                    $result->incorrectans = $incorrectanswers;
                    break;

                case constants::TYPE_PASSAGEREADING:
                    $result->hascorrectanswer = false;
                    $result->hasincorrectanswer = false;
                    if(isset($result->resultsdata)
                        && isset($result->resultsdata->read)
                        && ($result->resultsdata->read + $result->resultsdata->unreached)> 0){
                        $result->hasanswerdetails = true;
                            $result->resultstemplate = 'passagereadingreviewresults';
                            $result->resultsdata->passagehtml = \mod_minilesson\aitranscriptutils::render_passage($items->{constants::READINGPASSAGE});
                        $result->resultsdatajson = json_encode($result->resultsdata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }else{
                        $result->hasanswerdetails = false;
                    }
                    break;

                case constants::TYPE_FREEWRITING:
                case constants::TYPE_FREESPEAKING:
                    $result->hascorrectanswer = false;
                    $result->hasincorrectanswer = false;
                    if(isset($result->resultsdata)) {
                        $result->hasanswerdetails = true;
                        //the free writing and reading both need to be told to show no reattempt button
                        $result->resultsdata->noreattempt = true;
                        $result->resultsdatajson = json_encode($result->resultsdata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }else{
                        $result->hasanswerdetails = false;
                    }
                    break;
                case constants::TYPE_CONVERSATION:  // TO DO how to handle this?
                case constants::TYPE_SPACEGAME: // TO DO how to handle this?
                default:
                    $result->hascorrectanswer = false;
                    $result->hasincorrectanswer = false;
                    $result->hasanswerdetails = false;
                    $result->correctans = [];
                    $result->incorrectans = [];
            }

            $result->index++;
            // Every item stars.
            if($result->grade == 0){
                $ystarcnt = 0;
            }else if($result->grade < 19) {
                $ystarcnt = 1;
            }else if($result->grade < 39) {
                $ystarcnt = 2;
            }else if($result->grade < 59) {
                $ystarcnt = 3;
            }else if($result->grade < 79) {
                $ystarcnt = 4;
            }else{
                $ystarcnt = 5;
            }
            $result->yellowstars = array_fill(0, $ystarcnt, true);
            $gstarcnt = 5 - $ystarcnt;
            $result->graystars = array_fill(0, $gstarcnt, true);

            $useresults[] = $result;
        }

        // output results and back to course button
        $tdata = new \stdClass();

        // Course name at top of page.
        $tdata->coursename = $course->fullname;
        // Item stars.
        if($latestattempt->sessionscore == 0){
            $ystarcnt = 0;
        }else if($latestattempt->sessionscore < 19) {
            $ystarcnt = 1;
        }else if($latestattempt->sessionscore < 39) {
            $ystarcnt = 2;
        }else if($latestattempt->sessionscore < 59) {
            $ystarcnt = 3;
        }else if($latestattempt->sessionscore < 79) {
            $ystarcnt = 4;
        }else{
            $ystarcnt = 5;
        }
        $tdata->yellowstars = array_fill(0, $ystarcnt, true);
        $gstarcnt = 5 - $ystarcnt;
        $tdata->graystars = array_fill(0, $gstarcnt, true);

        $tdata->total = $latestattempt->sessionscore;
        $tdata->courseurl = $CFG->wwwroot . '/course/view.php?id=' .
            $latestattempt->courseid . '#section-'. ($cm->section - 1);

        // depending on finish screen settings and if its a teacher report
        if($teacherreport){
            $tdata->showfullresults = true;
            $tdata->results = $useresults;

        } else {
            switch ($moduleinstance->finishscreen) {
                case constants::FINISHSCREEN_FULL:
                case constants::FINISHSCREEN_CUSTOM:
                    $tdata->results = $useresults;
                    $tdata->showfullresults = true;
                    break;

                case constants::FINISHSCREEN_SIMPLE:
                default:
                    $tdata->results = [];
            }
        }

        // output reattempt button
        if($canattempt && !$teacherreport){
            $reattempturl = new \moodle_url( constants::M_URL . '/view.php',
                    ['n' => $latestattempt->moduleid, 'retake' => 1, 'embed' => $embed]);
            $tdata->reattempturl = $reattempturl->out();
        }
        // show back to course button if we are not in a tab or embedded
        if(!$config->enablesetuptab && $embed == 0 && !$teacherreport &&
            $moduleinstance->pagelayout !== 'embedded' &&
            $moduleinstance->pagelayout !== 'popup') {
            $tdata->backtocourse = true;
        }

        if($moduleinstance->finishscreen == constants::FINISHSCREEN_CUSTOM && !$teacherreport){
            // here we fetch the mustache engine, reset the loader to string loader
            // render the custom finish screen, and restore the original loader
            $mustache = $this->get_mustache();
            $oldloader = $mustache->getLoader();
            $mustache->setLoader(new \Mustache_Loader_StringLoader());
            $tpl = $mustache->loadTemplate($moduleinstance->finishscreencustom);
            $finishedcontents = $tpl->render($tdata);
            $mustache->setLoader($oldloader);
        }else{
            $finishedcontents = $this->render_from_template(constants::M_COMPONENT . '/quizfinished', $tdata);
        }

        // put it all in a div and return it
        $finisheddiv = \html_writer::div($finishedcontents , constants::M_QUIZ_FINISHED,
                ['id' => constants::M_QUIZ_FINISHED, 'style' => 'display: block']);

        return  $finisheddiv;
    }

    /**
     *  Show quiz container
     */
    public function show_quiz($comptest, $moduleinstance) {

        // quiz data
        $quizdata = $comptest->fetch_test_data_for_js();
        $itemshtml = [];
        foreach($quizdata as $item){
            $itemshtml[] = $this->render_from_template(constants::M_COMPONENT . '/' . $item->type, $item);
            // $this->page->requires->js_call_amd(constants::M_COMPONENT . '/' . $item->type, 'init', array($item));
        }

        $finisheddiv = \html_writer::div("" , constants::M_QUIZ_FINISHED,
            ['id' => constants::M_QUIZ_FINISHED]);

        $placeholderdiv = \html_writer::div('', constants::M_QUIZ_PLACEHOLDER . ' ' . constants::M_QUIZ_SKELETONBOX,
            ['id' => constants::M_QUIZ_PLACEHOLDER]);

        $quizclass = constants::M_QUIZ_CONTAINER . ' ' . $moduleinstance->csskey . ' '. constants::M_COMPONENT . '_' . $moduleinstance->containerwidth;
        $quizattributes = ['id' => constants::M_QUIZ_CONTAINER];
        if(!empty($moduleinstance->lessonfont)){
            $quizattributes['style'] = "font-family: '$moduleinstance->lessonfont', serif;";
        }
        $quizdiv = \html_writer::div($finisheddiv.implode('', $itemshtml) , $quizclass, $quizattributes);

        $ret = $placeholderdiv  . $quizdiv;
        return $ret;
    }

    /**
     *  Show quiz container
     */
    public function show_quiz_preview($comptest, $qid) {

        // quiz data
        $quizdata = $comptest->fetch_test_data_for_js();
        $itemshtml = [];
        foreach($quizdata as $item) {
            if ($item->id == $qid) {
                $itemshtml[] = $this->render_from_template(constants::M_COMPONENT . '/' . $item->type, $item);
            }
        }

        $quizdiv = \html_writer::div(implode('', $itemshtml) , constants::M_QUIZ_CONTAINER,
                ['id' => constants::M_QUIZ_CONTAINER]);

        $ret = $quizdiv;
        return $ret;
    }

    /**
     *  Show a progress circle overlay while uploading
     */
    public function show_progress($minilesson, $cm) {
        $hider = \html_writer::div('', constants::M_HIDER, ['id' => constants::M_HIDER]);
        $message = \html_writer::tag('h4', get_string('processing', constants::M_COMPONENT), []);
        $spinner = \html_writer::tag('i', '', ['class' => 'fa fa-spinner fa-5x fa-spin']);
        $progressdiv = \html_writer::div($message . $spinner , constants::M_PROGRESS_CONTAINER,
            ['id' => constants::M_PROGRESS_CONTAINER]);
        $ret = $hider . $progressdiv;
        return $ret;
    }

    /**
     * Show the feedback set in the activity settings
     */
    public function show_feedback($minilesson, $showtitle) {
        $thetitle = $this->output->heading($showtitle, 3, 'main');
        $displaytext = \html_writer::div($thetitle , constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_start();
        $displaytext .= \html_writer::div($minilesson->feedback, constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_end();
        $ret = \html_writer::div($displaytext, constants::M_FEEDBACK_CONTAINER, ['id' => constants::M_FEEDBACK_CONTAINER]);
        return $ret;
    }

    /**
     * Show the feedback set in the activity settings
     */
    public function show_title_postattempt($minilesson, $showtitle) {
        $thetitle = $this->output->heading($showtitle, 3, 'main');
        $displaytext = \html_writer::div($thetitle , constants::M_CLASS  . '_center');
        $ret = \html_writer::div($displaytext, constants::M_FEEDBACK_CONTAINER . ' ' . constants::M_POSTATTEMPT, ['id' => constants::M_FEEDBACK_CONTAINER]);
        return $ret;
    }

    /**
     * Show error (but when?)
     */
    public function show_error($minilesson, $cm) {
        $displaytext = $this->output->box_start();
        $displaytext .= $this->output->heading(get_string('errorheader', constants::M_COMPONENT), 3, 'main');
        $displaytext .= \html_writer::div('error message here', '', []);
        $displaytext .= $this->output->box_end();
        $ret = \html_writer::div($displaytext, constants::M_ERROR_CONTAINER, ['id' => constants::M_ERROR_CONTAINER]);
        return $ret;
    }


    function fetch_activity_amd($cm, $moduleinstance, $previewquestionid=0, $canreattempt=false, $embed=0) {
        global $CFG, $USER;
        // any html we want to return to be sent to the page
        $rethtml = '';

        // here we set up any info we need to pass into javascript

        $recopts = [];
        // recorder html ids
        $recopts['recorderid'] = constants::M_RECORDERID;
        $recopts['recordingcontainer'] = constants::M_RECORDING_CONTAINER;
        $recopts['recordercontainer'] = constants::M_RECORDER_CONTAINER;

        // activity html ids
        $recopts['passagecontainer'] = constants::M_PASSAGE_CONTAINER;
        $recopts['instructionscontainer'] = constants::M_INSTRUCTIONS_CONTAINER;
        $recopts['recordbuttoncontainer'] = constants::M_RECORD_BUTTON_CONTAINER;
        $recopts['startbuttoncontainer'] = constants::M_START_BUTTON_CONTAINER;
        $recopts['hider'] = constants::M_HIDER;
        $recopts['progresscontainer'] = constants::M_PROGRESS_CONTAINER;
        $recopts['feedbackcontainer'] = constants::M_FEEDBACK_CONTAINER;
        $recopts['wheretonextcontainer'] = constants::M_WHERETONEXT_CONTAINER;
        $recopts['quizcontainer'] = constants::M_QUIZ_CONTAINER;
        $recopts['errorcontainer'] = constants::M_ERROR_CONTAINER;

        // first confirm we are authorised before we try to get the token
        $config = get_config(constants::M_COMPONENT);
        if(empty($config->apiuser) || empty($config->apisecret)){
            $errormessage = get_string('nocredentials', constants::M_COMPONENT,
                    $CFG->wwwroot . constants::M_PLUGINSETTINGS);
            return $this->show_problembox($errormessage);
        } else {
            // fetch token
            $token = utils::fetch_token($config->apiuser, $config->apisecret);

            // check token authenticated and no errors in it
            $errormessage = utils::fetch_token_error($token);
            if(!empty($errormessage)){
                return $this->show_problembox($errormessage);
            }
        }
        $recopts['token'] = $token;
        $recopts['owner'] = hash('md5', $USER->username);
        $recopts['region'] = $moduleinstance->region;
        $recopts['ttslanguage'] = $moduleinstance->ttslanguage;
        $recopts['stt_guided'] = $moduleinstance->transcriber == constants::TRANSCRIBER_POODLL;

        $recopts['courseurl'] = $CFG->wwwroot . '/course/view.php?id=' .
            $moduleinstance->course  . '#section-'. ($cm->section - 1);

        $recopts['useanimatecss'] = $config->animations == constants::M_ANIM_FANCY;

        // to show a post item results panel
        $recopts['showitemreview'] = $moduleinstance->showitemreview ? true : false;

        // the activity URL for returning to on finished
        $activityurl = new \moodle_url(constants::M_URL . '/view.php',
            ['n' => $moduleinstance->id]);

        // add embedding url param if we are embedded
        if($embed > 0) {
            $activityurl->param('embed', $embed);
        }
        // set the activity url
        $recopts['activityurl'] = $activityurl->out();

        // the reattempturl if its ok
        $recopts['reattempturl'] = "";
        if($canreattempt) {
            $activityurl->param('retake', '1');
            $recopts['reattempturl'] = $activityurl->out();
        }

        // show back to course button if we are not in an iframe
        if($config->enablesetuptab ||
            $moduleinstance->pagelayout == 'embedded' ||
            $moduleinstance->pagelayout == 'popup' ||
            $embed > 0) {
            $recopts['backtocourse'] = '';
        }else{
            $recopts['backtocourse'] = true;
        }

        // quiz data
        $comptest = new comprehensiontest($cm);
        $quizdata = $comptest->fetch_test_data_for_js($this);
        if($previewquestionid){
            foreach($quizdata as $item){
                if($item->id == $previewquestionid){
                    $item->preview = true;
                    $recopts['quizdata'] = [$item];
                    break;
                }
            }
        }else {
            $recopts['quizdata'] = $quizdata;
        }

        // this inits the M.mod_minilesson thingy, after the page has loaded.
        // we put the opts in html on the page because moodle/AMD doesn't like lots of opts in js
        // convert opts to json
        $jsonstring = json_encode($recopts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if(($jsonstring) === false){
            $err = json_last_error();
        }
        $widgetid = constants::M_RECORDERID . '_opts_9999';
        $optshtml = \html_writer::tag('input', '', ['id' => 'amdopts_' . $widgetid, 'type' => 'hidden', 'value' => $jsonstring]);

        // the recorder div
        $rethtml = $rethtml . $optshtml;

        $opts = ['cmid' => $cm->id, 'widgetid' => $widgetid];
        $this->page->requires->js_call_amd("mod_minilesson/activitycontroller", 'init', [$opts]);

        // these need to be returned and echo'ed to the page
        return $rethtml;
    }

    /**
     * Return HTML to embed a minilesson
     */
    public function embed_minilesson($cmid, $token) {
        global $DB;
        $cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $comptest = new \mod_minilesson\comprehensiontest($cm);
        $previewid = 0;
        $embed = 1;
        $canattempt = true;
        $ret = $this->show_quiz($comptest, $moduleinstance);
        $ret .= $this->fetch_activity_amd($cm, $moduleinstance, $previewid, $canattempt, $embed);
        return $ret;
    }

    /**
     * Return HTML to display message about problem
     */
    public function show_problembox($msg) {
        $output = '';
        $output .= $this->output->box_start(constants::M_COMPONENT . '_problembox');
        $output .= $this->notification($msg, 'warning');
        $output .= $this->output->box_end();
        return $output;
    }

    public function show_open_close_dates($moduleinstance) {
        $tdata = [];
        if($moduleinstance->viewstart > 0){$tdata['opendate'] = $moduleinstance->viewstart;
        }
        if($moduleinstance->viewend > 0){$tdata['closedate'] = $moduleinstance->viewend;
        }
        $ret = $this->output->render_from_template( constants::M_COMPONENT . '/openclosedates', $tdata);
        return $ret;
    }

    public function push_buttons_menu($cm, $clonecount) {
        $templateitems = [];
        $pushthings = ['maxattempts', 'transcriber', 'showitemreview'];

        foreach ($pushthings as $pushthing) {
            switch($pushthing){
                case 'transcriber': $action = constants::M_PUSH_TRANSCRIBER;
                    break;
                case 'showimageflip': $action = constants::M_PUSH_SHOWITEMREVIEW;
                    break;
                case 'maxattempts': $action = constants::M_PUSH_MAXATTEMPTS;
                    break;
            }
            $thepushbutton = new \single_button(new \moodle_url( constants::M_URL . '/push.php',
                    ['id' => $cm->id, 'action' => $action]), get_string('push', constants::M_COMPONENT));
            $thepushbutton->add_confirm_action(get_string('pushconfirm', constants::M_COMPONENT, ['pushthing' => $pushthing, 'clonecount' => $clonecount]));

            $templateitems[] = ['title' => get_string($pushthing, constants::M_COMPONENT),
                'description' => get_string($pushthing . '_details', constants::M_COMPONENT),
                'content' => $this->render($thepushbutton)];
        }

        // Generate and return menu
        $ret = $this->output->render_from_template( constants::M_COMPONENT . '/manybuttonsmenu', ['items' => $templateitems]);

        return $ret;

    }

}
