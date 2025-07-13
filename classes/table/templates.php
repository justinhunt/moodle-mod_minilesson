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

namespace mod_minilesson\table;

use action_link;
use confirm_action;
use context_module;
use mod_minilesson\constants;
use moodle_url;
use stdClass;

/**
 * Class templates
 *
 * @package    mod_minilesson
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class templates extends dynamictable {

    protected $cm;

    protected $renderer;

    protected $strings = [];

    public function set_filterset(\core_table\local\filter\filterset $filterset): void {
        global $PAGE;
        $cmid = $filterset->get_filter('cmid')->current();
        $this->cm = get_coursemodule_from_id('minilesson', $cmid);
        parent::set_filterset($filterset);

        $cols['id'] = get_string('col:templateid', constants::M_COMPONENT);
        $cols['name'] = get_string('col:name', constants::M_COMPONENT);
        $cols['timemodified'] = get_string('col:timemodified', constants::M_COMPONENT);
        $cols['action'] = get_string('col:action', constants::M_COMPONENT);
        $this->define_columns(array_keys($cols));
        $this->define_headers(array_values($cols));

        $this->collapsible(false);

        $this->renderer = $PAGE->get_renderer('mod_minilesson');
        $this->strings['edit'] = get_string('action:edittemplate', constants::M_COMPONENT);
        $this->strings['delete'] = get_string('action:deletetemplate', constants::M_COMPONENT);
        $this->strings['duplicate'] = get_string('action:duplicatetemplate', constants::M_COMPONENT);


        $this->set_sql('*', '{minilesson_templates}', '1 = 1');
        $this->sortable(true, 'id', SORT_DESC);
    }

    public function guess_base_url(): void {
        $this->define_baseurl(new moodle_url(constants::M_URL . '/aigen_dev.php', ['id' => $this->cm->id]));
    }

    public function get_context(): \context {
        return context_module::instance($this->cm->id);
    }

    public function col_action(stdClass $record) {
        $editbutton = new action_link(
            new moodle_url($this->baseurl, ['action' => 'edit', 'templateid' => $record->id]),
             $this->renderer->pix_icon('t/edit', $this->strings['edit']));
        $o[] = $this->renderer->render($editbutton);

        $duplicatebutton = new action_link(
            new moodle_url($this->baseurl, ['action' => 'duplicate', 'templateid' => $record->id,  'sesskey' => sesskey()]),
             $this->renderer->pix_icon('t/copy', $this->strings['duplicate']));
        $o[] = $this->renderer->render($duplicatebutton);

        $deletebutton = new action_link(
            new moodle_url($this->baseurl, ['action' => 'delete', 'templateid' => $record->id, 'sesskey' => sesskey()]),
             $this->renderer->pix_icon('t/delete', $this->strings['delete']));
        $deletebutton->add_action(new confirm_action(get_string('templatedeleteconfirmation', constants::M_COMPONENT)));
        $o[] = $this->renderer->render($deletebutton);

        return join(' ',$o);
    }

    public function col_timemodified(stdClass $record) {
        return $record->timemodified > 0 ? userdate($record->timemodified): '';
    }

    public function has_capability(): bool {
        return has_capability('mod/minilesson:managetemplate', $this->get_context());
    }

}
