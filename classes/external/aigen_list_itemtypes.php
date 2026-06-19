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
use core_plugin_manager;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use mod_minilesson\constants;
use mod_minilesson\utils;

/**
 * Class aigen_list_itemtypes
 *
 * Lists the available (enabled) minilesson item types and their descriptions, so that an
 * AI agent can choose the correct item types/templates to use when generating a lesson.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_list_itemtypes extends external_api {
    /**
     * parameters for list itemtypes
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * List all enabled minilesson item types and their descriptions.
     * @return array
     */
    public static function execute() {

        $context = context_system::instance();
        self::validate_context($context);

        $availableitems = core_plugin_manager::instance()->get_plugins_of_type(constants::SUBPLUGINTYPES['item']);

        $responseitemtypes = [];
        foreach ($availableitems as $plugininfo) {
            if (!$plugininfo->is_enabled()) {
                continue;
            }

            $description = '';
            if (get_string_manager()->string_exists('item_desc', $plugininfo->component)) {
                $description = (string) $plugininfo->get_description();
            }

            $skills = [];
            $itemtypeclass = utils::fetch_itemtype_classname($plugininfo->name);
            if ($itemtypeclass && isset($itemtypeclass::$skills)) {
                $skills = $itemtypeclass::$skills;
            }

            $responseitemtypes[] = [
                'type' => $plugininfo->name,
                'name' => get_string('pluginname', $plugininfo->component),
                'description' => $description,
                'skills' => array_values($skills),
            ];
        }

        return $responseitemtypes;
    }

    /**
     * return list of item types
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'type' => new external_value(PARAM_ALPHANUMEXT, 'The item type machine name (used as the item "type")'),
                'name' => new external_value(PARAM_TEXT, 'The human readable name of the item type'),
                'description' => new external_value(PARAM_RAW, 'A description of what the item type does'),
                'skills' => new external_multiple_structure(
                    new external_value(PARAM_ALPHA, 'A language skill (listening, speaking, reading, writing, '
                        . 'pronunciation, vocabulary, grammar) or "content" for display-only item types'),
                    'The language skills this item type focuses on'
                ),
            ])
        );
    }
}
