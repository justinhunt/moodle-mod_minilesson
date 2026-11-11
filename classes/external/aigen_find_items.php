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

use context_course;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_minilesson\constants;
use mod_minilesson\local\aigen\itemeditor;

/**
 * Searches the items of the minilessons in a course, or of chosen minilessons.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_find_items extends external_api {
    /** @var int The most matches returned when the caller does not say. */
    const DEFAULT_MAX = 100;

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(
                PARAM_INT,
                'Search every minilesson of this course. Give 0 to search only the lessons in cmids.',
                VALUE_DEFAULT,
                0
            ),
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'A minilesson Course Module ID'),
                'Search these minilessons (as well as those of courseid, if given)',
                VALUE_DEFAULT,
                []
            ),
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Only items of this type, e.g. multichoice', VALUE_DEFAULT, ''),
            'wherejson' => new external_value(
                PARAM_RAW,
                'Only items whose fields have exactly these values, as a JSON object by import field name: '
                . '{"listenorread": "1", "hideanswertext": "2"}. A list field matches a list of the same lines.',
                VALUE_DEFAULT,
                ''
            ),
            'text' => new external_value(
                PARAM_RAW,
                'Only items with this text (any case) in any of their text fields, e.g. "(A)"',
                VALUE_DEFAULT,
                ''
            ),
            'maxresults' => new external_value(
                PARAM_INT,
                'The most items to return (default 100)',
                VALUE_DEFAULT,
                self::DEFAULT_MAX
            ),
        ]);
    }

    /**
     * Find items.
     *
     * @param int $courseid a course to search
     * @param int[] $cmids lessons to search
     * @param string $type an item type
     * @param string $wherejson exact field values
     * @param string $text text to look for
     * @param int $maxresults the most to return
     * @return array
     */
    public static function execute(
        $courseid = 0,
        $cmids = [],
        $type = '',
        $wherejson = '',
        $text = '',
        $maxresults = self::DEFAULT_MAX
    ) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmids' => $cmids,
            'type' => $type,
            'wherejson' => $wherejson,
            'text' => $text,
            'maxresults' => $maxresults,
        ]);
        $response = ['success' => false, 'matches' => [], 'searched' => 0, 'truncated' => false, 'errormsg' => ''];

        $where = [];
        if (trim($params['wherejson']) !== '') {
            $where = json_decode($params['wherejson'], true);
            if (!is_array($where) || array_is_list($where) && $where) {
                $response['errormsg'] = get_string('edit:badwhere', constants::M_COMPONENT);
                return $response;
            }
        }
        $needle = \core_text::strtolower(trim($params['text']));

        $cmids = array_map('intval', $params['cmids']);
        if ($params['courseid']) {
            $coursecontext = context_course::instance($params['courseid']);
            self::validate_context($coursecontext);
            foreach (get_fast_modinfo($params['courseid'])->get_instances_of(constants::M_MODNAME) as $cm) {
                if (!$cm->deletioninprogress && $cm->uservisible) {
                    $cmids[] = (int) $cm->id;
                }
            }
        }
        $cmids = array_values(array_unique($cmids));
        if (!$cmids) {
            $response['errormsg'] = get_string('edit:nowheretosearch', constants::M_COMPONENT);
            return $response;
        }

        raise_memory_limit(MEMORY_HUGE);
        $max = max(1, min(1000, (int) $params['maxresults']));
        foreach ($cmids as $cmid) {
            $editor = itemeditor::for_cmid($cmid);
            $context = $editor->get_context();
            // A lesson the caller may not export is skipped, not an error: a course search covers all of them.
            if (!has_capability('mod/minilesson:export', $context)) {
                continue;
            }
            self::validate_context($context);
            $response['searched']++;
            $lessonname = get_fast_modinfo(get_coursemodule_from_id(constants::M_MODNAME, $cmid)->course)
                ->get_cm($cmid)->name;
            foreach ($editor->export_items()->items as $item) {
                if ($params['type'] !== '' && $item->type !== $params['type']) {
                    continue;
                }
                $matched = [];
                foreach ($where as $field => $value) {
                    if (!property_exists($item, $field) || self::flatten($item->{$field}) !== self::flatten($value)) {
                        continue 2;
                    }
                    $matched[$field] = self::flatten($item->{$field});
                }
                if ($needle !== '') {
                    $found = false;
                    foreach ($item as $field => $value) {
                        if (in_array($field, ['itemid', 'version', 'position', 'filesid', 'type'])) {
                            continue;
                        }
                        $flat = self::flatten($value);
                        if (\core_text::strpos(\core_text::strtolower($flat), $needle) !== false) {
                            $matched[$field] = $flat;
                            $found = true;
                        }
                    }
                    if (!$found) {
                        continue;
                    }
                }
                if (count($response['matches']) >= $max) {
                    $response['truncated'] = true;
                    break 2;
                }
                $response['matches'][] = [
                    'cmid' => $cmid,
                    'lessonname' => $lessonname,
                    'itemid' => $item->itemid,
                    'version' => $item->version,
                    'position' => $item->position,
                    'type' => $item->type,
                    'name' => (string) ($item->name ?? ''),
                    'matchedjson' => json_encode((object) $matched, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
            }
        }
        $response['success'] = true;
        return $response;
    }

    /**
     * A field value as one string: a list as its lines.
     *
     * @param mixed $value
     * @return string
     */
    protected static function flatten($value): string {
        if (is_array($value) || is_object($value)) {
            return implode("\n", array_map(function ($line) {
                return is_scalar($line) ? (string) $line : json_encode($line);
            }, array_values((array) $value)));
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return trim((string) $value);
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if the search ran'),
            'matches' => new external_multiple_structure(
                new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'The minilesson'),
                    'lessonname' => new external_value(PARAM_RAW, 'The minilesson\'s name'),
                    'itemid' => new external_value(PARAM_INT, 'The item'),
                    'version' => new external_value(PARAM_INT, 'The item\'s version, for aigen_update_items'),
                    'position' => new external_value(PARAM_INT, 'Its 1-based position in the lesson'),
                    'type' => new external_value(PARAM_RAW, 'The item type'),
                    'name' => new external_value(PARAM_RAW, 'The item name'),
                    'matchedjson' => new external_value(
                        PARAM_RAW,
                        'JSON object of the fields that matched, with their values (lists as lines)'
                    ),
                ])
            ),
            'searched' => new external_value(PARAM_INT, 'How many minilessons were searched'),
            'truncated' => new external_value(PARAM_BOOL, 'True if there were more matches than maxresults'),
            'errormsg' => new external_value(PARAM_RAW, 'Why nothing was searched, when success is false'),
        ]);
    }
}
