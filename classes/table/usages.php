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

use html_writer;
use mod_minilesson\aigen;
use mod_minilesson\constants;
use pix_icon;
use stdClass;

/**
 * Class usages
 *
 * @package    mod_minilesson
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usages extends templates {

    protected $templates;

    public function set_filterset(\core_table\local\filter\filterset $filterset): void {
        global $DB, $PAGE;
        $cmid = $filterset->get_filter('cmid')->current();
        $this->cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid);
        dynamictable::set_filterset($filterset);

        $ids = null;
        if ($filterset->has_filter('ids')) {
            $ids = $filterset->get_filter('ids')->get_filter_values();
        }

        $cols['name'] = get_string('col:name', constants::M_COMPONENT);
        $cols['timecreated'] = get_string('col:timecreated', constants::M_COMPONENT);
        $cols['timemodified'] = get_string('col:timemodified', constants::M_COMPONENT);
        $cols['progress'] = get_string('col:progress', constants::M_COMPONENT);
        $this->define_columns(array_keys($cols));
        $this->define_headers(array_values($cols));

        $this->collapsible(false);
        $this->sortable(false, 'id', SORT_DESC);

        if (AJAX_SCRIPT) {
            $PAGE->set_context($this->get_context());
        }

        $this->templates = aigen::fetch_lesson_templates();
        $this->renderer = $PAGE->get_renderer('mod_minilesson');

        $sqlwhere = 'minilessonid = :minilessonid';
        if (!empty($ids)) {
            [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $sqlwhere .= ' AND id ' . $in;
        } else {
            $sqlwhere .= ' AND COALESCE(progress, 0) < 1';
        }
        $params['minilessonid'] = $this->cm->instance;
        $this->set_sql('*', '{minilesson_template_usages}', $sqlwhere, $params);
        $this->set_attribute('data-updateinterval', 2);
    }

    public function col_name(stdClass $record) {
        if (array_key_exists($record->templateid, $this->templates)) {
            return $this->templates[$record->templateid]['name'];
        }
        return '';
    }

    public function col_timecreated(stdClass $record) {
        return $record->timecreated > 0 ? userdate($record->timecreated): '';
    }

    public function col_progress(stdClass $record) {
        if ($record->progress == 1) { // Complete.
            $icon = $this->renderer->render(new pix_icon('i/checked', get_string('successful', constants::M_COMPONENT)));
            $status = html_writer::span($icon, 'action-icon');
        } else {
            $data = [
                'id' => $record->id, 'width' => floor(100 * $record->progress),
                'inprogress' => !is_null($record->progress), 'tableuniqueid' => $this->uniqueid
            ];
            $status = $this->renderer->render_from_template(constants::M_COMPONENT . '/aigen_progress', $data);
        }
        return $status;
    }

    public function needs_update(string $column) {
        return in_array($column, ['timemodified', 'progress']);
    }

}
