<?php

use mod_minilesson\constants;

require('../../config.php');

$show    = optional_param('show', '', PARAM_PLUGIN);
$hide    = optional_param('hide', '', PARAM_PLUGIN);


$url = new moodle_url('/mod/minilesson/itemtypes.php', []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('manageminilessonitem', 'mod_minilesson'));
$PAGE->set_heading(get_string('manageminilessonitem', 'mod_minilesson'));

require_login();

$qtypes = constants::ITEMTYPES;
$table = new html_table();
$table->head  = array(get_string('itemname', 'minilesson'), get_string('itemcount', 'minilesson'), get_string('action'));
$table->colclasses = array('leftalign', 'centeralign', 'centeralign');
$table->data  = array();
$table->attributes['class'] = 'admintable generaltable';
$table->id = 'managelessonitems';

$enabledplugin = get_config('minilesson', 'enableditems');
if (empty($enabledplugin)) {
    $enableditems = array();
} else {
    $enableditems = explode(',', $enabledplugin);
}

$manageurl = new moodle_url('/mod/minilesson/manage.php', ['sesskey' => sesskey()]);
foreach ($qtypes as $qtype) {
    $count = $DB->count_records('minilesson_rsquestions', ['type' => $qtype]);
    if (in_array($qtype, $enableditems)) {
        $manageurl->params(['action' => 'disable', 'qtype' => $qtype]);
        $hideurl = $manageurl->out(false);
        $hideshow = "<a href=\"$hideurl\">";
        $hideshow .= $OUTPUT->pix_icon('t/hide', get_string('disable')) . '</a>';
    } else {
        $manageurl->params(['action' => 'enable', 'qtype' => $qtype]);
        $showurl = $manageurl->out(false);
        $hideshow = "<a href=\"$showurl\">";
        $hideshow .= $OUTPUT->pix_icon('t/show', get_string('enable')) . '</a>';
    }
    $image = new moodle_url('/mod/minilesson/pix/' . $qtype . '.png', ['ver' => $CFG->themerev]);
    $table->data[] = [
        html_writer::tag('img', '', ['src' => $image->out(false), 'class' => 'itemimg']) . ' 
        ' . get_string('add' . $qtype . 'item', constants::M_COMPONENT),
        $count,
        $hideshow
    ];
}

echo $OUTPUT->header();

echo html_writer::table($table);

echo $OUTPUT->footer();
