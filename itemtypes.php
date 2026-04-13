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
 * item types management page
 *
 * @package    mod_minilesson
 * @copyright  2020 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_minilesson\constants;

require_once(dirname(__FILE__, 3) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');

$show = optional_param('show', '', PARAM_PLUGIN);
$hide = optional_param('hide', '', PARAM_PLUGIN);

admin_externalpage_setup('manageminilessonitem');
$PAGE->set_heading(get_string('manageminilessonitem', 'mod_minilesson'));

$qtypes = core_plugin_manager::instance()->get_plugins_of_type(constants::SUBPLUGINTYPES['item']);
$table = new html_table();
$table->head = [get_string('itemname', 'minilesson'), get_string('itemcount', 'minilesson'), get_string('action')];
$table->colclasses = ['leftalign', 'centeralign', 'centeralign'];
$table->data = [];
$table->attributes['class'] = 'admintable generaltable';
$table->id = 'managelessonitems';

$manageurl = new moodle_url('/mod/minilesson/manage.php', ['sesskey' => sesskey()]);
/** @var \mod_minilesson\plugininfo\minilessonitem  $qplugininfo */
foreach ($qtypes as $qplugininfo) {
    $count = $DB->count_records('minilesson_rsquestions', ['type' => $qplugininfo->name]);
    if ($qplugininfo->is_enabled()) {
        $manageurl->params(['action' => 'disable', 'qtype' => $qplugininfo->name]);
        $hideurl = $manageurl->out(false);
        $hideshow = "<a href=\"$hideurl\">";
        $hideshow .= $OUTPUT->pix_icon('t/hide', get_string('disable')) . '</a>';
    } else {
        $manageurl->params(['action' => 'enable', 'qtype' => $qplugininfo->name]);
        $showurl = $manageurl->out(false);
        $hideshow = "<a href=\"$showurl\">";
        $hideshow .= $OUTPUT->pix_icon('t/show', get_string('enable')) . '</a>';
    }
    $table->data[] = [
        html_writer::img(
            $qplugininfo->get_logo_url(),
            $qplugininfo->component,
            ['class' => 'itemimg']
        ) . $qplugininfo->get_add_label(),
        $count,
        $hideshow,
    ];
}

echo $OUTPUT->header();
echo html_writer::tag(
    'div',
    get_string('manageminilessonitems_explanation', 'mod_minilesson'),
[
    'class' => 'ml_manage_items_explanation',
]
);
echo html_writer::table($table);

echo $OUTPUT->footer();