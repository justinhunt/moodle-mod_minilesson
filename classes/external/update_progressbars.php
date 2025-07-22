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

namespace mod_minilesson\external;

use context;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use mod_minilesson\table\usages;

require_once($CFG->libdir . '/externallib.php');

/**
 * Class update_progressbars
 *
 * @package    mod_minilesson
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_progressbars extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'contextid'),
            'ids' => new external_multiple_structure(new external_value( PARAM_INT, 'id'))
        ]);
    }

    public static function execute($contextid, $ids) {
        $params = self::validate_parameters(self::execute_parameters(), ['contextid' => $contextid, 'ids' => $ids]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);

        require_capability('mod/minilesson:managetemplate', $context);

        $table = new usages();
        $filterset = usages::get_filterset_object()
            ->upsert_filter('cmid', (int) $context->instanceid)
            ->upsert_filter('ids', $params['ids']);
        $table->set_filterset($filterset);
        $table->setup();
        $table->query_db(0);
        $responserows = [];
        foreach($table->rawdata as $row) {
            $formattedrow = $table->format_row($row);
            $responserow = [];
            foreach($formattedrow as $col => $data) {
                $responserow[] = [
                    'column' => $col,
                    'data' => $data,
                    'update' => $table->needs_update($col)
                ];
            }
            $responserows[] = [
                'id' => $row->id,
                'columns' => $responserow
            ];
        }
        return $responserows;
    }

    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'id'),
                'columns' => new external_multiple_structure(
                    new external_single_structure([
                        'column' => new external_value(PARAM_ALPHANUMEXT, 'column name'),
                        'data' => new external_value(PARAM_RAW, 'column data'),
                        'update' => new external_value(PARAM_BOOL, 'needs to update column data')
                    ])
                )
            ])
        );
    }

}
