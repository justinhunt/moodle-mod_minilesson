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

/**
 * Reports for minilesson
 *
 *
 * @package    mod_minilesson
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

use \mod_minilesson\constants;
use \mod_minilesson\utils;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // minilesson instance ID
$format = optional_param('format', 'html', PARAM_TEXT); //export format csv or html
$showreport = optional_param('report', 'menu', PARAM_TEXT); // report type
$userid = optional_param('userid', 0, PARAM_INT); // report type
$attemptid = optional_param('attemptid', 0, PARAM_INT); // report type


//paging details
$paging = new stdClass();
$paging->perpage = optional_param('perpage',-1, PARAM_INT);
$paging->pageno = optional_param('pageno',0, PARAM_INT);
$paging->sort  = optional_param('sort','iddsc', PARAM_TEXT);


if ($id) {
    $cm         = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance  = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);
} elseif ($n) {
    $moduleinstance  = $DB->get_record(constants::M_TABLE, array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

$PAGE->set_url(constants::M_URL . '/reports.php',
	array('id' => $cm->id,'report'=>$showreport,'format'=>$format,'userid'=>$userid,'attemptid'=>$attemptid));
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

require_capability('mod/minilesson:evaluate', $modulecontext);

//Get an admin settings 
$config = get_config(constants::M_COMPONENT);

//set per page according to admin setting
if($paging->perpage==-1){
		$paging->perpage = $config->itemsperpage;
}



// Trigger module viewed event.
$event = \mod_minilesson\event\course_module_viewed::create(array(
   'objectid' => $moduleinstance->id,
   'context' => $modulecontext
));
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot(constants::M_MODNAME, $moduleinstance);
$event->trigger();


/// Set up the page header
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

if($moduleinstance->foriframe==1 || $moduleinstance->pagelayout=='embedded') {
    $PAGE->set_pagelayout('embedded');
}elseif($config->enablesetuptab  || $moduleinstance->pagelayout=='popup'){
    $PAGE->set_pagelayout('popup');
}else{
    $PAGE->set_pagelayout('incourse');
}

//20210601 - we probably dont need this ... delete soon
//$PAGE->requires->jquery();


//This puts all our display logic into the renderer.php files in this plugin
$renderer = $PAGE->get_renderer(constants::M_COMPONENT);
$reportrenderer = $PAGE->get_renderer(constants::M_COMPONENT,'report');

//From here we actually display the page.
//this is core renderer stuff
$mode = "reports";
$extraheader="";
switch ($showreport){

	//not a true report, separate implementation in renderer
	case 'menu':
		echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('reports', constants::M_COMPONENT));
		echo $reportrenderer->render_reportmenu($moduleinstance,$cm);

		//show backtotop button in most cases
        if(!$config->enablesetuptab) {
            echo $renderer->backtotopbutton($course->id);
        }

        // Finish the page
		echo $renderer->footer();
		return;

	case 'basic':
		$report = new \mod_minilesson\report\basic();
		//formdata should only have simple values, not objects
		//later it gets turned into urls for the export buttons
		$formdata = new stdClass();
		break;

    //list view of attempts and grades and action links
    case 'attempts':
        $report = new \mod_minilesson\report\attempts();
        $formdata = new stdClass();
        $formdata->moduleid = $moduleinstance->id;
        $formdata->modulecontextid = $modulecontext->id;
        $formdata->groupmenu = true;
        break;

    //list view of attempts and grades and action links
    case 'courseattempts':
        $report = new \mod_minilesson\report\courseattempts();
        $formdata = new stdClass();
        $formdata->moduleid = $moduleinstance->id;
        $formdata->courseid = $moduleinstance->course;
        $formdata->modulecontextid = $modulecontext->id;
        $formdata->groupmenu = true;
        break;


    //list view of attempts and grades and action links
    case 'attemptresults':
        $report = new \mod_minilesson\report\attemptresults();
        $formdata = new stdClass();
        $formdata->moduleid = $moduleinstance->id;
        $formdata->attemptid = $attemptid;
        $formdata->modulecontextid = $modulecontext->id;
        $formdata->groupmenu = true;
        break;


    case 'incompleteattempts':
        $report = new \mod_minilesson\report\incompleteattempts();
        $formdata = new stdClass();
        $formdata->moduleid = $moduleinstance->id;
        $formdata->modulecontextid = $modulecontext->id;
        $formdata->groupmenu = true;
        break;

    //list view of attempts and grades and action links
    //same as "grading" mainly. Just for report not action purposes
	case 'gradereport':
		$report = new \mod_minilesson\report\gradereport();
		$formdata = new stdClass();
		$formdata->moduleid = $moduleinstance->id;
		$formdata->modulecontextid = $modulecontext->id;
        $formdata->groupmenu = true;
		break;

    //list view of attempts and grades and action links
    case 'grading':
        $report = new \mod_minilesson\report\grading();
        //formdata should only have simple values, not objects
        //later it gets turned into urls for the export buttons
        $formdata = new stdClass();
        $formdata->moduleid = $moduleinstance->id;
        $formdata->modulecontextid = $modulecontext->id;
        $formdata->groupmenu = true;
        break;

    //Show a single attempt, basically the students finished view of the attempt for the teacher
    case 'viewattempt':
        $attempt = $DB->get_record(constants::M_ATTEMPTSTABLE,array('id'=>$attemptid));
        if($attempt) {
            if ($attempt->userid === $USER->id || has_capability('mod/minilesson:canmanageattempts', $modulecontext)) {
                $comptest = new \mod_minilesson\comprehensiontest($cm);
                echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('reports', constants::M_COMPONENT));
                $attemptuser = $DB->get_record('user', array('id' => $attempt->userid));
                echo $renderer->heading(get_string('attemptfor', constants::M_COMPONENT, fullname($attemptuser)), 3);
                $teacherreport = true;
                echo $renderer->show_finished_results($comptest, $attempt, $cm, false, false, $teacherreport);
                $link = new \moodle_url(constants::M_URL . '/reports.php', array('report' => 'menu', 'id' => $cm->id, 'n' => $moduleinstance->id));
                echo  \html_writer::link($link, get_string('returntoreports', constants::M_COMPONENT));
                echo $renderer->footer();
                return;
            }
        }
        break;
		
	default:
		echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('reports', constants::M_COMPONENT));
		echo "unknown report type.";
        //backtotop
        echo $renderer->backtotopbutton($course->id);
		echo $renderer->footer();
		return;
}

/*
1) load the class
2) call report->process_raw_data
3) call $rows=report->fetch_formatted_records($withlinks=true(html) false(print/excel))
5) call $reportrenderer->render_section_html($sectiontitle, $report->name, $report->get_head, $rows, $report->fields);
*/
$groupmenu = '';
if(isset($formdata->groupmenu)){
    // fetch groupmode/menu/id for this activity
    if ($groupmode = groups_get_activity_groupmode($cm)) {
        $groupmenu = groups_print_activity_menu($cm, $PAGE->url, true);
        $groupmenu .= ' ';
        $formdata->groupid = groups_get_activity_group($cm);
    }else{
        $formdata->groupid  = 0;
    }
}else{
    $formdata->groupid  = 0;
}

$report->process_raw_data($formdata);
$reportheading = $report->fetch_formatted_heading();

switch($format){
	case 'csv':
		$reportrows = $report->fetch_formatted_rows(false);
		$reporttitle = $reportheading . '_' . $course->shortname . '_' . $moduleinstance->name . '_' . date(DATE_ATOM);
		$reportrenderer->render_section_csv($reporttitle, $report->fetch_name(), $report->fetch_head(), $reportrows, $report->fetch_fields());
		exit;
	default:

        if($config->reportstable == constants::M_USE_DATATABLES){
            $pagetitle =get_string('reports', constants::M_COMPONENT);
            $reportrows = $report->fetch_formatted_rows(true);
            $allrowscount = $report->fetch_all_rows_count();

            //css must be required before header sent out
            $PAGE->requires->css( new \moodle_url('https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css'));
            echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('reports', constants::M_COMPONENT));
            //echo $renderer->heading($pagetitle);
            //echo $renderer->navigation($moduleinstance, 'reports');
            echo $extraheader;
            echo $groupmenu;
            echo $reportrenderer->render_section_html($reportheading, $report->fetch_name(), $report->fetch_head(), $reportrows,
                $report->fetch_fields());
        }else{
            $pagetitle =get_string('reports', constants::M_COMPONENT);
            $reportrows = $report->fetch_formatted_rows(true, $paging);
            $allrowscount = $report->fetch_all_rows_count();

            $pagingbar = $reportrenderer->show_paging_bar($allrowscount, $paging, $PAGE->url);
            echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('reports', constants::M_COMPONENT));
            //echo $renderer->heading($pagetitle);
            //echo $renderer->navigation($moduleinstance, 'reports');
            echo $extraheader;
            echo $groupmenu;
            echo $pagingbar;
            echo $reportrenderer->render_section_html($reportheading, $report->fetch_name(), $report->fetch_head(), $reportrows,
                $report->fetch_fields());
            echo $pagingbar;
        }
        echo $reportrenderer->show_reports_footer($moduleinstance,$cm,$formdata,$showreport);
        //back to course button if not in frame
        if(!$config->enablesetuptab) {
            echo $renderer->backtotopbutton($course->id);
        }
        echo $renderer->footer();
	    /*
		$reportrows = $report->fetch_formatted_rows(true,$paging);
		$allrowscount = $report->fetch_all_rows_count();
		$pagingbar = $reportrenderer->show_paging_bar($allrowscount, $paging,$PAGE->url);
		echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('reports', constants::M_COMPONENT));
		echo $extraheader;
        echo $groupmenu;
		echo $pagingbar;
		echo $reportrenderer->render_section_html($reportheading, $report->fetch_name(), $report->fetch_head(), $reportrows, $report->fetch_fields());
		echo $pagingbar;
		echo $reportrenderer->show_reports_footer($moduleinstance,$cm,$formdata,$showreport);
        //back to course button if not in frame
		if(!$config->enablesetuptab) {
            echo $renderer->backtotopbutton($course->id);
        }
		echo $renderer->footer();
	    */
}