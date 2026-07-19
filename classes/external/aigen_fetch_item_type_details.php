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

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_minilesson\local\itemtype\item;

/**
 * Class aigen_fetch_item_type_details
 *
 * Returns the machine readable import field spec for one minilesson item type, so that an
 * AI agent can compose item import JSON directly and submit it via
 * mod_minilesson_aigen_import_items_json.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_fetch_item_type_details extends external_api {
    /**
     * parameters for fetch item type details
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'itemtype' => new external_value(
                PARAM_ALPHANUMEXT,
                'The item type machine name, as returned by mod_minilesson_aigen_list_itemtypes (e.g. "multichoice")'
            ),
        ]);
    }

    /**
     * Fetch the import field spec for one item type.
     * @param string $itemtype the item type machine name
     * @return array
     */
    public static function execute($itemtype) {

        $params = self::validate_parameters(self::execute_parameters(), ['itemtype' => $itemtype]);

        $context = context_system::instance();
        self::validate_context($context);

        $itemtypeclass = item::get_itemtype_class($params['itemtype']);
        if (!$itemtypeclass) {
            throw new \invalid_parameter_exception('Unknown item type: ' . $params['itemtype']);
        }

        $spec = $itemtypeclass::aigen_fetch_import_spec();
        if ($spec === null) {
            return [
                'itemtype' => $params['itemtype'],
                'documented' => false,
            ];
        }

        return [
            'itemtype' => $params['itemtype'],
            'documented' => true,
            'usage' => $spec['usage'] . ' ' . item::AIGEN_FILES_CONVENTION,
            'fields' => $spec['fields'],
            'fileareas' => $spec['fileareas'],
            'examplejson' => json_encode($spec['example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * return structure of the item type import spec
     * @return external_single_structure
     */
    public static function execute_returns() {
        $fieldstructure = new external_single_structure([
            'jsonname' => new external_value(PARAM_RAW, 'The JSON property name to use in the item object'),
            'type' => new external_value(
                PARAM_ALPHANUMEXT,
                'Value type: string, int, boolean (accepts true/yes/1 or false/no/0), stringarray '
                . '(a JSON array of strings), voice (a TTS voice display name or "auto"), voiceopts '
                . '(normal/slow/veryslow/SSML), layout (horizontal/vertical/magazine/auto), or anonymousfile '
                . '(supplied via the files payload, not as an item property)'
            ),
            'required' => new external_value(PARAM_BOOL, 'True if the field must be present'),
            'default' => new external_value(
                PARAM_RAW,
                'The default applied when the field is omitted, as a string; empty string if none'
            ),
            'description' => new external_value(PARAM_RAW, 'What the field does'),
            'options' => new external_multiple_structure(
                new external_single_structure([
                    'value' => new external_value(PARAM_RAW, 'An allowed value, as a string'),
                    'meaning' => new external_value(PARAM_RAW, 'What that value means'),
                ]),
                'The allowed values and their meanings; absent for free-value fields',
                VALUE_OPTIONAL
            ),
            'example' => new external_value(
                PARAM_RAW,
                'An example value, as a string (a JSON array string for stringarray fields)',
                VALUE_OPTIONAL
            ),
        ]);
        $fileareastructure = new external_single_structure([
            'filearea' => new external_value(
                PARAM_ALPHANUMEXT,
                'File area key to use inside files.{filesid} in the import payload'
            ),
            'description' => new external_value(PARAM_RAW, 'What the files in this area are used for'),
            'filenames' => new external_value(PARAM_RAW, 'The filename convention for this file area'),
        ]);

        return new external_single_structure([
            'itemtype' => new external_value(PARAM_ALPHANUMEXT, 'The item type machine name'),
            'documented' => new external_value(
                PARAM_BOOL,
                'False if no detailed import docs exist yet for this item type; all other keys are then absent. '
                . 'Undocumented types can still be imported, but only via the template based aigen functions.'
            ),
            'usage' => new external_value(
                PARAM_RAW,
                'Prose guidance on composing an import item of this type, including the shared files/filesid '
                . 'base64 convention of the payload',
                VALUE_OPTIONAL
            ),
            'fields' => new external_multiple_structure(
                $fieldstructure,
                'The supported import fields for this item type; only use fields listed here',
                VALUE_OPTIONAL
            ),
            'fileareas' => new external_multiple_structure(
                $fileareastructure,
                'File areas this item type accepts via the base64 files payload',
                VALUE_OPTIONAL
            ),
            'examplejson' => new external_value(
                PARAM_RAW,
                'A complete, valid example import payload as a JSON string (parse it): an object with an "items" '
                . 'array and optionally a "files" object, suitable for mod_minilesson_aigen_import_items_json',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
