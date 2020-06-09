<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/06/26
 * Time: 13:16
 */

namespace mod_poodlltime\output;

use html_writer;
use \mod_poodlltime\constants;
use \mod_poodlltime\utils;
use \mod_poodlltime\flower;
use \mod_poodlltime\comprehensiontest;

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

        $output .= $this->output->heading($activityname);
        if (has_capability('mod/poodlltime:evaluate', $context)) {
            //   $output .= $this->output->heading_with_help($activityname, 'overview', constants::M_COMPONENT);

            if (!empty($currenttab)) {
                ob_start();
                include($CFG->dirroot.'/mod/poodlltime/tabs.php');
                $output .= ob_get_contents();
                ob_end_clean();
            }
        }


        return $output;
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

        $output .= $this->output->heading($activityname);

        return $output;
    }

    /**
     * Return HTML to display limited header
     */
    public function notabsheader(){
        return $this->output->header();
    }
    /**
     * Return a message that something is not right
     */
    public function thatsnotright($message){

       $ret = $this->output->heading(get_string('thatsnotright',constants::M_COMPONENT),3);
       $ret .= \html_writer::div($message,constants::M_CLASS  . '_thatsnotright_message');
        return $ret;
    }

    public function backtotopbutton($courseid){

        $button = $this->output->single_button(new \moodle_url( '/course/view.php',
            array('id'=>$courseid)),get_string('backtotop',constants::M_COMPONENT));

        $ret = \html_writer::div($button ,constants::M_CLASS  . '_backtotop_cont');
        return $ret;
    }


    /**
     *
     */
    public function reattemptbutton($moduleinstance){

        $button = $this->output->single_button(new \moodle_url(constants::M_URL . '/view.php',
            array('n'=>$moduleinstance->id,'retake'=>1)),get_string('reattempt',constants::M_COMPONENT));

        $ret = \html_writer::div($button ,constants::M_CLASS  . '_afterattempt_cont');
        return $ret;

    }

    /**
     *
     */
    public function show_wheretonext($moduleinstance){

        $nextactivity = utils::fetch_next_activity($moduleinstance->activitylink);
        //show activity link if we are up to it
        if ($nextactivity->url) {
            $button= $this->output->single_button($nextactivity->url,$nextactivity->label);
        //else lets show a back to top link
        }else {
            $button = $this->output->single_button(new \moodle_url(constants::M_URL . '/view.php',
                array('n' => $moduleinstance->id)), get_string('backtotop', constants::M_COMPONENT));
        }
        $ret = \html_writer::div($button ,constants::M_WHERETONEXT_CONTAINER);
        return $ret;

    }


    public function pushpassage_button($cm){

        $thetitle =  $this->output->heading(get_string('pushpassage',constants::M_COMPONENT), 3, 'main');
        $displaytext =  \html_writer::div($thetitle ,constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_start();
        $button = $this->output->single_button(new \moodle_url( constants::M_URL . '/push.php',
            array('id'=>$cm->id,'action'=>constants::M_PUSH_PASSAGE)),get_string('pushpassage',constants::M_COMPONENT));
        $displaytext .= $button;
        $displaytext .= $this->output->box_end();
        $ret= \html_writer::div($displaytext);
        return $ret;
    }

    public function pushalternatives_button($cm){

        $thetitle =  $this->output->heading(get_string('pushalternatives',constants::M_COMPONENT), 3, 'main');
        $displaytext =  \html_writer::div($thetitle ,constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_start();
        $button = $this->output->single_button(new \moodle_url( constants::M_URL . '/push.php',
            array('id'=>$cm->id,'action'=>constants::M_PUSH_ALTERNATIVES)),get_string('pushalternatives',constants::M_COMPONENT));
        $displaytext .= $button;
        $displaytext .= $this->output->box_end();
        $ret= \html_writer::div($displaytext);
        return $ret;
    }

    public function pushquestions_button($cm){

        $thetitle =  $this->output->heading(get_string('pushquestions',constants::M_COMPONENT), 3, 'main');
        $displaytext =  \html_writer::div($thetitle ,constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_start();
        $button = $this->output->single_button(new \moodle_url( constants::M_URL . '/push.php',
            array('id'=>$cm->id,'action'=>constants::M_PUSH_QUESTIONS)),get_string('pushquestions',constants::M_COMPONENT));
        $displaytext .= $button;
        $displaytext .= $this->output->box_end();
        $ret= \html_writer::div($displaytext);
        return $ret;
    }

    public function pushlevel_button($cm){

        $thetitle =  $this->output->heading(get_string('pushlevel',constants::M_COMPONENT), 3, 'main');
        $displaytext =  \html_writer::div($thetitle ,constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_start();
        $button = $this->output->single_button(new \moodle_url( constants::M_URL . '/push.php',
                array('id'=>$cm->id,'action'=>constants::M_PUSH_LEVEL)),get_string('pushlevel',constants::M_COMPONENT));
        $displaytext .= $button;
        $displaytext .= $this->output->box_end();
        $ret= \html_writer::div($displaytext);
        return $ret;
    }

    /**
     *
     */
    public function show_machineregradeallbutton($moduleinstance){
        $options=[];
        $button = $this->output->single_button(new \moodle_url(constants::M_URL . '/gradesadmin.php',
            array('n'=>$moduleinstance->id, 'action'=>'machineregradeall')),get_string('machineregradeall',constants::M_COMPONENT),'post',$options);

        $ret = \html_writer::div($button ,constants::M_GRADESADMIN_CONTAINER);
        return $ret;
    }

    /**
     *
     */
    public function show_pushmachinegrades($moduleinstance){

        $sectiontitle= get_string("pushmachinegrades",constants::M_COMPONENT);
        $heading = $this->output->heading($sectiontitle, 4);

        if(utils::can_transcribe($moduleinstance) && $moduleinstance->machgrademethod==constants::MACHINEGRADE_MACHINE){
            $options=[];
        }else{
            $options=array('disabled'=>'disabled');
        }
        $button = $this->output->single_button(new \moodle_url(constants::M_URL . '/gradesadmin.php',
            array('n'=>$moduleinstance->id, 'action'=>'pushmachinegrades')),get_string('pushmachinegrades',constants::M_COMPONENT),'post',$options);

        $ret = \html_writer::div($heading . $button ,constants::M_GRADESADMIN_CONTAINER);
        return $ret;
    }

    /**
     * @param array an array of mistranscription objects (passageindex, passageword, mistranscription summary)
     * @return string an html table
     */
    public function show_all_mistranscriptions($items){

        global $CFG;

        //set up our table
        $tableattributes = array('class' => 'generaltable ' . constants::M_CLASS . '_table');

        $htmltable = new \html_table();
        $tableid = \html_writer::random_id(constants::M_COMPONENT);
        $htmltable->id = $tableid;
        $htmltable->attributes = $tableattributes;

        $head=array(get_string('passageindex',constants::M_COMPONENT),
            get_string('passageword',constants::M_COMPONENT),
            get_string('mistrans_count',constants::M_COMPONENT),
            get_string('mistranscriptions',constants::M_COMPONENT));

        $htmltable->head = $head;
        $rowcount=0;
        $total_mistranscriptions=0;
        foreach ($items as $row) {
            //if this was not a mistranscription, skip
            if(!$row->mistranscriptions){continue;}
            $rowcount++;
            $htr = new \html_table_row();

            $cell = new \html_table_cell($row->passageindex);
            $cell->attributes = array('class' => constants::M_CLASS . '_cell_passageindex');
            $htr->cells[] = $cell;

            $cell = new \html_table_cell($row->passageword);
            $cell->attributes = array('class' => constants::M_CLASS . '_cell_passageword');
            $htr->cells[] = $cell;

            $showmistranscriptions = "";
            $mistrans_count = 0;
            foreach($row->mistranscriptions as $badword=>$count){
                if($showmistranscriptions != ""){$showmistranscriptions .= " | ";}
                $showmistranscriptions .= $badword . "(" . $count . ")";
                $mistrans_count+=$count;
            }
            $total_mistranscriptions+=$mistrans_count;

            $cell = new \html_table_cell($mistrans_count);
            $cell->attributes = array('class' => constants::M_CLASS . '_cell_mistrans_count');
            $htr->cells[] = $cell;

            $cell = new \html_table_cell($showmistranscriptions);
            $cell->attributes = array('class' => constants::M_CLASS . '_cell_mistranscriptions');
            $htr->cells[] = $cell;


            $htmltable->data[] = $htr;
        }
        $tabletitle= get_string("mistranscriptions_summary",constants::M_COMPONENT);
        $html = $this->output->heading($tabletitle, 4);
        if ($rowcount==0) {
            $html .= get_string("nomistranscriptions",constants::M_COMPONENT);
        }else{
            $html .= \html_writer::tag('span',get_string("total_mistranscriptions",
                constants::M_COMPONENT,$total_mistranscriptions),array('class'=>constants::M_CLASS . '_totalmistranscriptions'));
            $html .= \html_writer::table($htmltable);

            //set up datatables
            $tableprops = new \stdClass();
            $opts =Array();
            $opts['tableid']=$tableid;
            $opts['tableprops']=$tableprops;
            $this->page->requires->js_call_amd( constants::M_COMPONENT. "/datatables", 'init', array($opts));
            $this->page->requires->css( new \moodle_url('https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css'));

        }
        return $html;
    }

    /**
     *
     */
    public function show_currenterrorestimate($errorestimate){
        $message = get_string("currenterrorestimate",constants::M_COMPONENT,$errorestimate);
        $ret = \html_writer::div($message ,constants::M_GRADESADMIN_CONTAINER);
        return $ret;

    }

    /**
     *
     */
    public function exceededattempts($moduleinstance){
        $message = get_string("exceededattempts",constants::M_COMPONENT,$moduleinstance->maxattempts);
        $ret = \html_writer::div($message ,constants::M_CLASS  . '_afterattempt_cont');
        return $ret;

    }

    public function show_ungradedyet(){
        $message = get_string("notgradedyet",constants::M_COMPONENT);
        $ret = \html_writer::div($message ,constants::M_CLASS  . '_ungraded_cont');
        return $ret;
    }

    /**
     *  Show grades admin heading
     */
    public function show_gradesadmin_heading($showtitle,$showinstructions) {
        $thetitle =  $this->output->heading($showtitle, 3, 'main');
        $displaytext =  \html_writer::div($thetitle ,constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_start();
        $displaytext .= \html_writer::div($showinstructions ,constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_end();
        $ret= \html_writer::div($displaytext);
        return $ret;
    }



    /**
     *  Show instructions/welcome
     */
    public function show_welcome($showtext, $showtitle) {
        $thetitle =  $this->output->heading($showtitle, 3, 'main');
        $displaytext =  \html_writer::div($thetitle ,constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_start();
        $displaytext .= \html_writer::div($showtext ,constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_end();
        $ret= \html_writer::div($displaytext,constants::M_INSTRUCTIONS_CONTAINER,array('id'=>constants::M_INSTRUCTIONS_CONTAINER));
        return $ret;
    }

    /**
     * Show the introduction text is as set in the activity description
     */
    public function show_intro($poodlltime,$cm){
        $ret = "";
        if (trim(strip_tags($poodlltime->intro))) {
            $ret .= $this->output->box_start('mod_introbox');
            $ret .= format_module_intro('poodlltime', $poodlltime, $cm->id);
            $ret .= $this->output->box_end();
        }
        return $ret;
    }

    /**
     * Show the reading passage after the attempt, basically set it to display on load and give it a background color
     */
    public function old_show_passage_postattempt($poodlltime){
        $ret = "";
        $ret .= \html_writer::div( $poodlltime->passage ,constants::M_PASSAGE_CONTAINER . ' ' . constants::M_POSTATTEMPT,
            array('id'=>constants::M_PASSAGE_CONTAINER));
        return $ret;
    }

    /**
     * Two column Summary
     */
    public function show_runningrecord_forprint($poodlltime,$cm,$gradenow,$markeduppassage=false)
    {
        global $CFG;
        $ret = "";
        $left = $this->fetch_runningrecord_passage_forprint($poodlltime,$cm,$markeduppassage);
        $leftcol = \html_writer::div($left, constants::M_TWOCOL_LEFTCOL . ' col-md-10');

        $wpmscore =  $gradenow->attemptdetails('wpm');
        $quizscore = $gradenow->attemptdetails('qscore');
        $audiourl = $gradenow->attemptdetails('audiourl');
        $backbutton =  \html_writer::link($CFG->wwwroot . '/course/view.php?id=' . $cm->course, get_string('back'),array('class'=>'btn btn-success'));
        $right = $this->render_wpmdetails($wpmscore) .
                $this->render_quizdetails($quizscore) .
                $this->render_mrseed_standing() .
                $this->render_tiny_audioplayer($audiourl) .
                $backbutton;
        $rightcol = \html_writer::div( $right, constants::M_TWOCOL_RIGHTCOL . " col-md-2");

        $row= \html_writer::div($leftcol . $rightcol,'row');
        $ret .= \html_writer::div($row,constants::M_TWOCOL_CONTAINER . ' container');
        return $ret;

    }

    /**
     * Show the reading passage after the attempt, basically set it to display on load and give it a background color
     */
    public function fetch_runningrecord_passage_forprint($poodlltime,$cm,$markeduppassage){

        $comp_test =  new comprehensiontest($cm);

        //passage picture
        if($poodlltime->passagepicture) {
            $zeroitem = new \stdClass();
            $zeroitem->id = 0;
            $picurl = $comp_test->fetch_media_url(constants::PASSAGEPICTURE_FILEAREA, $zeroitem);
            $picture = \html_writer::img($picurl, '', array('role' => 'decoration'));
            $picturecontainer = \html_writer::div($picture, constants::M_COMPONENT . '-passage-pic');
        }else{
            $picturecontainer ='';
        }

        //passage
        if($markeduppassage){
            $passage = $markeduppassage;
        }else{
            $passage =  utils::lines_to_brs($poodlltime->passage);
        }

        $ret = "";
        $ret .= \html_writer::div( $picturecontainer . $passage ,constants::M_PASSAGE_CONTAINER . ' '  . constants::M_MSV_MODE . ' '  . constants::M_POSTATTEMPT,
                array('id'=>constants::M_PASSAGE_CONTAINER));

        return $ret;
    }


    /**
     * Two column Summary
     */
    public function show_twocol_summary($poodlltime,$cm,$gradenow,$markeduppassage=false)
    {
        global $CFG;
        $ret = "";
        $left = $this->show_passage_postattempt($poodlltime,$cm,$markeduppassage);
        $leftcol = \html_writer::div($left, constants::M_TWOCOL_LEFTCOL . ' col-md-10');

        $wpmscore =  $gradenow->attemptdetails('wpm');
        $quizscore = $gradenow->attemptdetails('qscore');
        $audiourl = $gradenow->attemptdetails('audiourl');
        $backbutton =  \html_writer::link($CFG->wwwroot . '/course/view.php?id=' . $cm->course, get_string('back'),array('class'=>'btn btn-success'));
        $right = $this->render_wpmdetails($wpmscore) .
            $this->render_quizdetails($quizscore) .
            $this->render_mrseed_standing() .
            $this->render_tiny_audioplayer($audiourl) .
            $backbutton;
        $rightcol = \html_writer::div( $right, constants::M_TWOCOL_RIGHTCOL . " col-md-2");

        $row= \html_writer::div($leftcol . $rightcol,'row');
        $ret .= \html_writer::div($row,constants::M_TWOCOL_CONTAINER . ' container');
        return $ret;

    }

    /**
     * Show the reading passage after the attempt, basically set it to display on load and give it a background color
     */
    public function show_passage_postattempt($poodlltime,$cm,$markeduppassage){

        $comp_test =  new comprehensiontest($cm);

        //passage picture
        if($poodlltime->passagepicture) {
            $zeroitem = new \stdClass();
            $zeroitem->id = 0;
            $picurl = $comp_test->fetch_media_url(constants::PASSAGEPICTURE_FILEAREA, $zeroitem);
            $picture = \html_writer::img($picurl, '', array('role' => 'decoration'));
            $picturecontainer = \html_writer::div($picture, constants::M_COMPONENT . '-passage-pic');
        }else{
            $picturecontainer ='';
        }

        //passage
        if($markeduppassage){
            $passage = $markeduppassage;
        }else{
            $passage =  utils::lines_to_brs($poodlltime->passage);
        }

        $ret = "";
        $ret .= \html_writer::div( $picturecontainer . $passage ,constants::M_PASSAGE_CONTAINER . ' '  . constants::M_POSTATTEMPT,
            array('id'=>constants::M_PASSAGE_CONTAINER));

        return $ret;
    }

    public function render_wpmdetails($scorevalue){
        global $CFG;
        $title = \html_writer::div(get_string('wpm',constants::M_COMPONENT),'panel-heading');
        $score = \html_writer::div($scorevalue,constants::M_GRADING_SCORE . ' panel-body',array('id'=>constants::M_GRADING_WPM_SCORE));
        $ret = \html_writer::div($title . $score ,constants::M_TWOCOL_WPM_CONTAINER . ' panel panel-primary',
            array('id'=>constants::M_TWOCOL_WPM_CONTAINER));
        return $ret;
    }
    public function render_quizdetails($scorevalue)
    {
        global $CFG;
        $title = \html_writer::div(get_string('quiz', constants::M_COMPONENT), 'panel-heading');
        $score = \html_writer::div($scorevalue . '%', constants::M_GRADING_SCORE . ' panel-body', array('id' => constants::M_GRADING_QUIZ_SCORE));
        $ret = \html_writer::div($title . $score , constants::M_TWOCOL_QUIZ_CONTAINER . ' panel panel-primary',
            array('id' => constants::M_TWOCOL_QUIZ_CONTAINER));
        return $ret;
    }

    public function render_tiny_audioplayer($audiourl){
        $audioplayer = \html_writer::tag('audio','',
            array('controls'=>'','src'=>$audiourl,'id'=>constants::M_TWOCOL_PLAYER,'class'=>constants::M_TWOCOL_PLAYER));
        $ret = \html_writer::div($audioplayer,constants::M_TWOCOL_PLAYER_CONTAINER,array('id'=>constants::M_TWOCOL_PLAYER_CONTAINER));
        return $ret;
    }

    public function render_mrseed_standing(){
        global $CFG;
        $picurl = $CFG->wwwroot . constants::M_URL . '/pix/static/mrseedstanding.png';
        $picture = \html_writer::img($picurl, '', array('role' => 'decoration'));
        $picturecontainer = \html_writer::div($picture, constants::M_COMPONENT . '_mrseed-standing');
        return $picturecontainer;
    }

    /**
     * Show the reading passage
     */
    public function show_passage($poodlltime,$cm){

        $ret = "";
        $ret .= \html_writer::div( $poodlltime->passage ,constants::M_PASSAGE_CONTAINER,
            array('id'=>constants::M_PASSAGE_CONTAINER));
        return $ret;
    }

    /**
     *  Show quiz container
     */
    public function show_quiz($cm,$moduleinstance){

        //quiz data
        $comp_test =  new \mod_poodlltime\comprehensiontest($cm);
        $quizdata = $comp_test->fetch_test_data_for_js();
        $itemshtml=[];
        foreach($quizdata as $item){
           $itemshtml[] = $this->render_from_template(constants::M_COMPONENT . '/' . $item->type, $item);
           // $this->page->requires->js_call_amd(constants::M_COMPONENT . '/' . $item->type, 'init', array($item));
        }

        $quizdiv = \html_writer::div(implode('',$itemshtml) ,constants::M_QUIZ_CONTAINER,
            array('id'=>constants::M_QUIZ_CONTAINER));
        $ret = $quizdiv;
        return $ret;
    }

    /**
     *  Show a progress circle overlay while uploading
     */
    public function show_progress($poodlltime,$cm){
        $hider =  \html_writer::div('',constants::M_HIDER,array('id'=>constants::M_HIDER));
        $message =  \html_writer::tag('h4',get_string('processing',constants::M_COMPONENT),array());
        $spinner =  \html_writer::tag('i','',array('class'=>'fa fa-spinner fa-5x fa-spin'));
        $progressdiv = \html_writer::div($message . $spinner ,constants::M_PROGRESS_CONTAINER,
            array('id'=>constants::M_PROGRESS_CONTAINER));
        $ret = $hider . $progressdiv;
        return $ret;
    }

    public function show_humanevaluated_message(){
        $displaytext = get_string('humanevaluatedmessage',constants::M_COMPONENT);
        $ret= \html_writer::div($displaytext,constants::M_EVALUATED_MESSAGE,array('id'=>constants::M_EVALUATED_MESSAGE));
        return $ret;
    }

    public function show_machineevaluated_message(){
        $displaytext = get_string('machineevaluatedmessage',constants::M_COMPONENT);
        $ret= \html_writer::div($displaytext,constants::M_EVALUATED_MESSAGE,array('id'=>constants::M_EVALUATED_MESSAGE));
        return $ret;
    }

    /**
     * Show the feedback set in the activity settings
     */
    public function show_feedback($poodlltime,$showtitle){
        $thetitle =  $this->output->heading($showtitle, 3, 'main');
        $displaytext =  \html_writer::div($thetitle ,constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_start();
        $displaytext .=  \html_writer::div($poodlltime->feedback,constants::M_CLASS  . '_center');
        $displaytext .= $this->output->box_end();
        $ret= \html_writer::div($displaytext,constants::M_FEEDBACK_CONTAINER,array('id'=>constants::M_FEEDBACK_CONTAINER));
        return $ret;
    }

    /**
     * Show the feedback set in the activity settings
     */
    public function show_title_postattempt($poodlltime,$showtitle){
        $thetitle =  $this->output->heading($showtitle, 3, 'main');
        $displaytext =  \html_writer::div($thetitle ,constants::M_CLASS  . '_center');
        $ret= \html_writer::div($displaytext,constants::M_FEEDBACK_CONTAINER . ' ' . constants::M_POSTATTEMPT,array('id'=>constants::M_FEEDBACK_CONTAINER));
        return $ret;
    }

    /**
     * Show error (but when?)
     */
    public function show_error($poodlltime,$cm){
        $displaytext = $this->output->box_start();
        $displaytext .= $this->output->heading(get_string('errorheader',constants::M_COMPONENT), 3, 'main');
        $displaytext .=  \html_writer::div(get_string('uploadconverterror',constants::M_COMPONENT),'',array());
        $displaytext .= $this->output->box_end();
        $ret= \html_writer::div($displaytext,constants::M_ERROR_CONTAINER,array('id'=>constants::M_ERROR_CONTAINER));
        return $ret;
    }

    /**
     * The html part of the recorder (js is in the fetch_activity_amd)
     */
    public function show_recorder($moduleinstance){
        global $CFG, $USER;

        //first confirm we are authorised before we try to get the token
        $config = get_config(constants::M_COMPONENT);
        if(empty($config->apiuser) || empty($config->apisecret)){
            $errormessage = get_string('nocredentials',constants::M_COMPONENT,
                    $CFG->wwwroot . constants::M_PLUGINSETTINGS);
            return $this->show_problembox($errormessage);
        }else {
            //fetch token
            $token = utils::fetch_token($config->apiuser,$config->apisecret);

            //check token authenticated and no errors in it
            $errormessage = utils::fetch_token_error($token);
            if(!empty($errormessage)){
                return $this->show_problembox($errormessage);
            }
        }

        //recorder
        //=======================================
        $hints = new \stdClass();
        $hints->allowearlyexit = $moduleinstance->allowearlyexit;

        //perhaps we want to force stereoaudio
        if ($moduleinstance->transcriber == constants::TRANSCRIBER_GOOGLECLOUDSPEECH ||
                $moduleinstance->submitrawaudio) {
            $hints->encoder = 'stereoaudio';
        }
        $string_hints = base64_encode (json_encode($hints));
        $can_transcribe = \mod_poodlltime\utils::can_transcribe($moduleinstance);
        $transcribe = $can_transcribe  ? $moduleinstance->transcriber : "0";
        $recorderdiv= \html_writer::div('', constants::M_CLASS  . '_center',
            array('id'=>constants::M_RECORDERID,
                'data-id'=>'therecorder',
                'data-parent'=>$CFG->wwwroot,
                 'data-owner'=>hash('md5',$USER->username),
                'data-localloading'=>'auto',
                'data-localloader'=>'/mod/poodlltime/poodllloader.html',
                'data-media'=>"audio",
                'data-appid'=>constants::M_COMPONENT,
                'data-type'=>"poodlltime",
                'data-width'=>"240",
                'data-height'=>"110",
                //'data-iframeclass'=>"letsberesponsive",
                'data-updatecontrol'=>constants::M_READING_AUDIO_URL,
                'data-timelimit'=> $moduleinstance->timelimit,
                'data-transcode'=>"1",
                'data-transcribe'=>$transcribe,
                'data-language'=>$moduleinstance->ttslanguage,
                'data-expiredays'=>$moduleinstance->expiredays,
                'data-region'=>$moduleinstance->region,
                'data-fallback'=>'warning',
                'data-hints'=>$string_hints,
                'data-token'=>$token //localhost
                //'data-token'=>"643eba92a1447ac0c6a882c85051461a" //cloudpoodll
            )
        );
        $containerdiv= \html_writer::div($recorderdiv,constants::M_RECORDER_CONTAINER . " " . constants::M_CLASS  . '_center',
            array('id'=>constants::M_RECORDER_CONTAINER));
        //=======================================


        $recordingdiv = \html_writer::div($containerdiv ,constants::M_RECORDING_CONTAINER);

        //prepare output
        $ret = "";
        $ret .=$recordingdiv;
        //return it
        return $ret;
    }


    function fetch_activity_amd($cm, $moduleinstance){
        global $CFG, $USER;
        //any html we want to return to be sent to the page
        $ret_html = '';

        //here we set up any info we need to pass into javascript

        $recopts =Array();
        //recorder html ids
        $recopts['recorderid'] = constants::M_RECORDERID;
        $recopts['recordingcontainer'] = constants::M_RECORDING_CONTAINER;
        $recopts['recordercontainer'] = constants::M_RECORDER_CONTAINER;

        //activity html ids
        $recopts['passagecontainer'] = constants::M_PASSAGE_CONTAINER;
        $recopts['instructionscontainer'] = constants::M_INSTRUCTIONS_CONTAINER;
        $recopts['recordbuttoncontainer'] =constants::M_RECORD_BUTTON_CONTAINER;
        $recopts['startbuttoncontainer'] =constants::M_START_BUTTON_CONTAINER;
        $recopts['hider']=constants::M_HIDER;
        $recopts['progresscontainer'] = constants::M_PROGRESS_CONTAINER;
        $recopts['feedbackcontainer'] = constants::M_FEEDBACK_CONTAINER;
        $recopts['wheretonextcontainer'] = constants::M_WHERETONEXT_CONTAINER;
        $recopts['quizcontainer'] = constants::M_QUIZ_CONTAINER;
        $recopts['errorcontainer'] = constants::M_ERROR_CONTAINER;
        $recopts['allowearlyexit'] =  $moduleinstance->allowearlyexit ? true :false;
        $recopts['picwhenreading']=$moduleinstance->picwhenreading? true :false;

        //first confirm we are authorised before we try to get the token
        $config = get_config(constants::M_COMPONENT);
        if(empty($config->apiuser) || empty($config->apisecret)){
            $errormessage = get_string('nocredentials',constants::M_COMPONENT,
                    $CFG->wwwroot . constants::M_PLUGINSETTINGS);
            return $this->show_problembox($errormessage);
        }else {
            //fetch token
            $token = utils::fetch_token($config->apiuser,$config->apisecret);

            //check token authenticated and no errors in it
            $errormessage = utils::fetch_token_error($token);
            if(!empty($errormessage)){
                return $this->show_problembox($errormessage);
            }
        }
        $recopts['token']=$token;
        $recopts['owner']=hash('md5',$USER->username);
        $recopts['region']=$moduleinstance->region;



        //quiz data
        $comp_test =  new comprehensiontest($cm);
        $recopts['quizdata']= $comp_test->fetch_test_data_for_js();

        //passage picture
        if($moduleinstance->passagepicture) {
            $zeroitem = new \stdClass();
            $zeroitem->id = 0;
            $recopts['passagepictureurl'] = $comp_test->fetch_media_url(constants::PASSAGEPICTURE_FILEAREA, $zeroitem);
        }else{
            $recopts['passagepictureurl'] ='';
        }

        //we need a control tp hold the recorded audio URL for the reading
        $ret_html = $ret_html . \html_writer::tag('input', '', array('id' => constants::M_READING_AUDIO_URL, 'type' => 'hidden'));



        //this inits the M.mod_poodlltime thingy, after the page has loaded.
        //we put the opts in html on the page because moodle/AMD doesn't like lots of opts in js
        //convert opts to json
        $jsonstring = json_encode($recopts);
        $widgetid = constants::M_RECORDERID . '_opts_9999';
        $opts_html = \html_writer::tag('input', '', array('id' => 'amdopts_' . $widgetid, 'type' => 'hidden', 'value' => $jsonstring));

        //the recorder div
        $ret_html = $ret_html . $opts_html;

        $opts=array('cmid'=>$cm->id,'widgetid'=>$widgetid);
        $this->page->requires->js_call_amd("mod_poodlltime/activitycontroller", 'init', array($opts));
        $this->page->requires->strings_for_js(array('gotnosound','done','beginreading'),constants::M_COMPONENT);

        //these need to be returned and echo'ed to the page
        return $ret_html;
    }

    function load_app($cm, $poodlltime, $lastattempt = null) {
        global $CFG, $USER;

        $config = get_config(constants::M_COMPONENT);

        //first confirm we are authorised before we try to load the react app
        //if the token or API creds are invalid we report that
        if(empty($config->apiuser) || empty($config->apisecret)){
            $errormessage = get_string('nocredentials',constants::M_COMPONENT,
                    $CFG->wwwroot . constants::M_PLUGINSETTINGS);
            return $this->show_problembox($errormessage);
        }else {
            //fetch token
            $token = utils::fetch_token($config->apiuser,$config->apisecret);

            //check token authenticated and no errors in it
            $errormessage = utils::fetch_token_error($token);
            if(!empty($errormessage)){
                return $this->show_problembox($errormessage);
            }
        }

        //now we have our token and look auth'd, so continue to load app
        $cantranscribe = utils::can_transcribe($poodlltime);
        $transcribe = $cantranscribe  ? $poodlltime->transcriber : "0";
        $comptest =  new comprehensiontest($cm);

        $opts = (object) [];
        $opts->cmid = $cm->id;
        $opts->firstname = $USER->firstname;
        $opts->wwwroot = $CFG->wwwroot;
        $opts->courseurl = $CFG->wwwroot . '/course/view.php?id=' . $cm->course;
        $opts->name = $poodlltime->name;
        $opts->welcome = $poodlltime->welcome;
        $opts->passage = utils::lines_to_brs($poodlltime->passage);
        $opts->passagepictureurl = null;
        $opts->quizdata = $comptest->fetch_test_data_for_js();
        $opts->attemptid = $lastattempt ? $lastattempt->id : null;  // When we resume an attempt.
        $opts->flower = $lastattempt ? flower::get_flower($lastattempt->flowerid) : flower::fetch_newflower();
        $opts->picwhenreading=$poodlltime->picwhenreading ? true :false;

        if ($poodlltime->passagepicture) {
            $opts->passagepictureurl = $comptest->fetch_media_url(constants::PASSAGEPICTURE_FILEAREA, (object) ['id' => 0]);
        }

        //prepare our hints that get passed through recorder
        $ohints = new \stdClass();
        $ohints->allowearlyexit = $poodlltime->allowearlyexit;

        //perhaps we want to force stereoaudio
        if ($poodlltime->transcriber == constants::TRANSCRIBER_GOOGLECLOUDSPEECH ||
                $poodlltime->submitrawaudio) {
            $ohints->encoder = 'stereoaudio';
        }
        $hints = base64_encode(json_encode($ohints));
        //$hints = base64_encode(json_encode((object) ['allowearlyexit' => $poodlltime->allowearlyexit]));

        $recconfig = (object) [];
        $recconfig->id = 'therecorder';
        $recconfig->parent = $CFG->wwwroot;
        $recconfig->owner = hash('md5',$USER->username);
        $recconfig->localloading = 'auto';
        $recconfig->localloader = '/mod/poodlltime/poodllloader.html';
        $recconfig->media = "audio";
        $recconfig->appid = constants::M_COMPONENT;
        $recconfig->type = "poodlltime"; // The recorder type, so until we make a poodlltime one, it's readaloud.
        $recconfig->width = "240";
        $recconfig->height = "110";
        $recconfig->iframeclass = "letsberesponsive";
        $recconfig->updatecontrol = constants::M_READING_AUDIO_URL;
        $recconfig->timelimit =  $poodlltime->timelimit;
        $recconfig->transcode = true;
        $recconfig->transcribe =  $transcribe;
        $recconfig->language = $poodlltime->ttslanguage;
        $recconfig->expiredays = $poodlltime->expiredays;
        $recconfig->region = $poodlltime->region;
        $recconfig->fallback = 'warning';
        $recconfig->hints = $hints;
        $recconfig->token = $token;

        $appid = html_writer::random_id('poodlltimeapp');
        $optsid = html_writer::random_id('poodlltimeopts');
        $recconfigid = html_writer::random_id('poodlltimerecconfig');

        $this->page->requires->js_call_amd("mod_poodlltime/app-loader", 'init', [$appid, $optsid, $recconfigid]);
        $this->page->requires->strings_for_js([
            'aisreading',
            'beginreading',
            'clickstartwhenready',
            'congratsyouread',
            'counttofive',
            'done',
            'gofullscreen',
            'gotnosound',
            'greatjobnpushnext',
            'hellopushspeak',
            'hellonpushstart',
            'nicereadinga',
            'pleasewait',
            'readagainandanswer',
            'readpassageagainandanswerquestions',
            'sayyourname',
            'teacherwillcheck',
            'goodjoba',
            'thanksa',
            'thisisnotcorrect',
            'tryagain',
            'thisiscorrect',
        ], constants::M_COMPONENT);

        $this->page->requires->strings_for_js([
            'next',
        ], 'core');

        $html = '';
        $html .= html_writer::tag('div', '', ['id' => $appid]);
        $html .= html_writer::tag('script', json_encode($opts), ['id' => $optsid, 'type' => 'application/json']);
        $html .= html_writer::tag('script', json_encode($recconfig), ['id' => $recconfigid, 'type' => 'application/json']);

        return $html;
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

}
