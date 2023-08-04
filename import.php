<?php
/**
 * Displays the import page for minilesson.
 *
 * @package mod_minilesson
 * @author  Justin Hunt - poodll.com
 */

use \mod_minilesson\constants;
use \mod_minilesson\utils;
use \mod_minilesson\local\importform\baseimportform;


require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/csvlib.class.php');

$cmid = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // minilesson instance ID
$leftover_rows = optional_param('leftover_rows', '', PARAM_TEXT);
$action = optional_param('action', null, PARAM_ALPHA);
$iid         = optional_param('iid', '', PARAM_INT);


if ($cmid) {
    $cm         = get_coursemodule_from_id('minilesson', $cmid, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance  = $DB->get_record('minilesson', array('id' => $cm->instance), '*', MUST_EXIST);
} elseif ($n) {
    $moduleinstance  = $DB->get_record('minilesson', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('minilesson', $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error('You must specify a course_module ID or an instance ID');
}


require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);
require_capability('mod/minilesson:manage',$modulecontext);

$pagetitle = format_string($moduleinstance->name, true, $course);
$pagetitle .= ': ' . get_string('import', constants::M_COMPONENT);
$baseurl = new moodle_url('/mod/minilesson/import.php', ['id' => $cmid]);
$formurl = new moodle_url($baseurl);
$term = null;

$PAGE->set_url($baseurl);
$PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, [context_course::instance($course->id)]));
$PAGE->set_title($pagetitle);
$mode='import';

//Get admin settings
$config = get_config(constants::M_COMPONENT);
if($config->enablesetuptab){
    $PAGE->set_pagelayout('popup');
}else{
    $PAGE->set_pagelayout('course');
}

$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

$form = new baseimportform($formurl->out(false),['leftover_rows'=>$leftover_rows]);

if ($data = $form->get_data()) {

        $iid = csv_import_reader::get_new_iid('importminilessonitems');
        $cir = new csv_import_reader($iid, 'importminilessonitems');

        $content = $form->get_file_content('importfile');

        $readcount = $cir->load_csv_content($content, $data->encoding, $data->delimiter_name);
        $csvloaderror = $cir->get_error();
        unset($content);

        if (!is_null($csvloaderror)) {
            print_error('csvloaderror', '', $baseurl, $csvloaderror);
        }




    $theimport = new \mod_minilesson\import($cir,$moduleinstance,$modulecontext,$course,$cm);
    echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('importing', constants::M_COMPONENT));
    echo $renderer->heading($pagetitle);
    echo $renderer->box(get_string('importresults',constants::M_COMPONENT), 'generalbox minilesson_importintro', 'intro');
    $theimport->import_process();
    echo $renderer->back_to_import_button($cm);
    echo $renderer->footer();
    die;
}




echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('import', constants::M_COMPONENT));
echo $renderer->heading($pagetitle);
echo $renderer->box(get_string('importinstructions',constants::M_COMPONENT), 'generalbox minilesson_importintro', 'intro');

$form->display();
/*
$table = new mod_wordcards_table_terms('tblterms', $mod);
$table->define_baseurl($PAGE->url);
$table->out(25, false);
*/
echo $renderer->footer();
