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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use mod_minilesson\aigen;

/**
 * Class aigen_list_templates
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_list_templates extends external_api {
    /**
     * parameters for list templates
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * list all templates.
     * @return array
     */
    public static function execute() {

        $context = context_system::instance();
        self::validate_context($context);

        $templates = aigen::fetch_lesson_templates();
        $responsetemplates = [];

        foreach ($templates as $thetemplate) {
            $mappings = $thetemplate['config']->fieldmappings;
            $items = $thetemplate['config']->items;
            $templatedata = $thetemplate['template'];
            $templateitems = $thetemplate['template']->items;
            $inputs = [];
            $outputs = [];
            foreach ($mappings as $fieldname => $fieldmapping) {
                if (!empty($fieldmapping->enabled)) {
                    $requirefields = [
                        'fieldname' => $fieldname,
                        'title' => $fieldmapping->title,
                        'type' => $fieldmapping->type,
                        'description' => $fieldmapping->description,
                    ];
                    $optionalfields = ($fieldmapping->type == 'dropdown') ? ['options' => $fieldmapping->options] : [];
                    $inputs[] = array_merge($requirefields, $optionalfields);
                }
            }
            $outputitems = [];
            $imagecount = 0;
            foreach ($items as $item) {
                $totalfiles = 0;
                if (!empty($templateitems)) {
                    $type = $templateitems[$item->itemnumber]->type;
                    $description = $templateitems[$item->itemnumber]->instructions;
                    $filesid = $templateitems[$item->itemnumber]->filesid;
                    if (!empty($templatedata->files->$filesid)) {
                        foreach ($templatedata->files->$filesid as $fileobject) {
                            $totalfiles += count((array) $fileobject);
                        }
                    }
                    $imagecount += $totalfiles;
                    $outputitems[] = [
                        'type' => $type,
                        'description' => $description
                    ];
                }
            }

            $outputs[] = [
                'itemcount' => count($items),
                'items' => $outputitems,
                'imagecount' => $imagecount,
            ];
            $responsetemplates[] = [
                'id' => $thetemplate['id'],
                'name' => $thetemplate['name'],
                'description' => $thetemplate['description'],
                'inputs' => $inputs,
                'outputs' => $outputs,
            ];
        }
        return $responsetemplates;
    }

    /**
     * return list of templates
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Id'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'description' => new external_value(PARAM_RAW, 'Description'),
                'inputs' => new external_multiple_structure(
                    new external_single_structure([
                        'fieldname' => new external_value(PARAM_TEXT, 'Field Name'),
                        'title' => new external_value(PARAM_TEXT, 'Field Title'),
                        'type' => new external_value(PARAM_TEXT, 'Field Type'),
                        'description' => new external_value(PARAM_RAW, 'Field Description', VALUE_DEFAULT, ''),
                        'options' => new external_multiple_structure(
                            new external_value(PARAM_TEXT, 'Option value'),
                            'List of options',
                            VALUE_OPTIONAL
                        ),
                    ]),
                ),
                'outputs' => new external_multiple_structure(
                    new external_single_structure([
                        'itemcount' => new external_value(PARAM_INT, 'Count of items'),
                        'items' => new external_multiple_structure(
                            new external_single_structure([
                                'type' => new external_value(PARAM_TEXT, 'Item type'),
                                'description' => new external_value(PARAM_RAW, 'Item Description')
                            ])
                        ),
                        'imagecount' => new external_value(PARAM_INT, 'Count of image')
                    ]),
                )
            ])
        );
    }
}

