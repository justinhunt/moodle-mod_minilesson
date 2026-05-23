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
 * Services definition.
 *
 * @package mod_minilesson
 * @author  Justin Hunt - poodll.com
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

        'mod_minilesson_report_step_grade' => array(
                'classname'   => 'mod_minilesson_external',
                'methodname'  => 'report_step_grade',
                'description' => 'Reports the grade of a step',
                'capabilities' => 'mod/minilesson:view',
                'type'        => 'write',
                'ajax'        => true,
        ),

        'mod_minilesson_check_by_phonetic' => array(
                'classname'   => 'mod_minilesson_external',
                'methodname'  => 'check_by_phonetic',
                'description' => 'compares a spoken phrase to a correct phrase by phoneme' ,
                'capabilities' => 'mod/minilesson:view',
                'type'        => 'read',
                'ajax'        => true,
        ),

        'mod_minilesson_compare_passage_to_transcript' => array(
            'classname'   => 'mod_minilesson_external',
            'methodname'  => 'compare_passage_to_transcript',
            'description' => 'compares a spoken phrase to a correct phrase' ,
            'capabilities' => 'mod/minilesson:view',
            'type'        => 'read',
            'ajax'        => true,
        ),

        'mod_minilesson_evaluate_transcript' => array(
                'classname'   => 'mod_minilesson_external',
                'methodname'  => 'evaluate_transcript',
                'description' => 'evaluate transcript',
                'capabilities' => 'mod/minilesson:view',
                'type'        => 'read',
                'ajax'        => true,
            ),

        'mod_minilesson_submit_mform' => array(
                'classname'   => 'mod_minilesson_external',
                'methodname'  => 'submit_mform',
                'description' => 'submits mform.',
                'capabilities' => 'mod/minilesson:managequestions',
                'type'        => 'write',
                'ajax'        => true,
        ),

        'mod_minilesson_delete_item' => array(
                'classname'   => 'mod_minilesson_external',
                'methodname'  => 'delete_item',
                'description' => 'delete item.',
                'capabilities' => 'mod/minilesson:managequestions',
                'type'        => 'write',
                'ajax'        => true,
        ),

        'mod_minilesson_move_item' => array(
                'classname'   => 'mod_minilesson_external',
                'methodname'  => 'move_item',
                'description' => 'move item.',
                'capabilities' => 'mod/minilesson:managequestions',
                'type'        => 'write',
                'ajax'        => true,
        ),

        'mod_minilesson_duplicate_item' => array(
            'classname'   => 'mod_minilesson_external',
            'methodname'  => 'duplicate_item',
            'description' => 'duplicate item.',
            'capabilities' => 'mod/minilesson:managequestions',
            'type'        => 'write',
            'ajax'        => true,
        ),

        'mod_minilesson_create_instance' => array(
            'classname'   => 'mod_minilesson_external',
            'methodname'  => 'create_instance',
            'description' => 'create a minilesson instance.',
            'capabilities' => 'mod/minilesson:addinstance',
            'type'        => 'write',
            'ajax'        => true,
        ),

        'mod_minilesson_update_progressbars' => array(
            'classname'   => 'mod_minilesson\external\update_progressbars',
            'methodname'  => 'execute',
            'description' => 'get progress bar status',
            'capabilities' => 'mod/minilesson:managetemplate',
            'type'        => 'read',
            'ajax'        => true,
        ),

        'mod_minilesson_refresh_token' => array(
                'classname'   => 'mod_minilesson_external',
                'methodname'  => 'refresh_token',
                'description' => 'refreshes the speech recognition token',
                'capabilities' => 'mod/minilesson:view',
                'type'        => 'read',
                'ajax'        => true,
         ),
        'mod_minilesson_lessonbank' => array(
                'classname' => 'mod_minilesson_external',
                'methodname'  => 'lessonbank',
                'description' => 'Lesson bank',
                'capabilities' => 'mod/minilesson:manage',
                'type'        => 'read',
                'ajax'        => true,
        ),

        'mod_minilesson_set_user_preference' => array(
            'classname'   => 'mod_minilesson_external',
            'methodname'  => 'set_user_preference',
            'description' => 'Sets a minilesson user preference',
            'capabilities' => 'mod/minilesson:view',
            'type'        => 'write',
            'ajax'        => true,
        ),

        'mod_minilesson_fetch_codeeditor_aihelp' => [
            'classname' => 'mod_minilesson_external',
            'methodname' => 'fetch_codeeditor_aihelp',
            'description' => 'Fetches AI help for the code editor',
            'type' => 'read',
            'ajax' => true,
        ],
        'mod_minilesson_aigen_list_templates' => [
            'classname' => 'mod_minilesson\external\aigen_list_templates',
            'methodname' => 'execute',
            'description' => 'Get a list of aigen templates for creating activities',
            'type' => 'read',
            'loginrequired' => true,
        ],
        'mod_minilesson_aigen_create_empty_lesson' => [
            'classname' => 'mod_minilesson\external\aigen_create_empty_lesson',
            'methodname' => 'execute',
            'description' => 'Creates an empty minilesson ',
            'type' => 'write',
            'capabilities' => 'mod/minilesson:addinstance',
            'loginrequired' => true,
        ],
        'mod_minilesson_aigen_create_add_items_to_lesson' => [
            'classname' => 'mod_minilesson\external\aigen_create_add_items_to_lesson',
            'methodname' => 'execute',
            'description' => 'Creates and adds items to minilesson',
            'type' => 'write',
            'loginrequired' => true,
        ],
        'mod_minilesson_aigen_list_minilessons' => [
            'classname' => 'mod_minilesson\external\aigen_list_minilessons',
            'methodname' => 'execute',
            'description' => 'Lists minilessons in course',
            'type' => 'read',
            'loginrequired' => true,
        ],
        'mod_minilesson_aigen_fetch_create_items_status' => [
            'classname' => 'mod_minilesson\external\aigen_fetch_create_items_status',
            'methodname' => 'execute',
            'description' => 'Checks the status of aigen_create_add_items_to_lesson task.',
            'type' => 'read',
            'loginrequired' => true,
        ],
        'mod_minilesson_list_courses' => [
            'classname' => 'mod_minilesson\external\list_courses',
            'methodname' => 'get_users_courses',
            'description' => 'List Courses.',
            'type' => 'read',
            'loginrequired' => true,
        ]
];

$services = [
    'AI Generation Service' => [
        'name' => 'AI Generation Service',
        'shortname' => 'aigenservice',
        'functions' => [
            'mod_minilesson_aigen_list_templates',
            'mod_minilesson_aigen_create_empty_lesson',
            'mod_minilesson_aigen_create_add_items_to_lesson',
            'mod_minilesson_aigen_list_minilessons',
            'mod_minilesson_aigen_fetch_create_items_status',
            'mod_minilesson_list_courses',
        ],
        'enabled' => 1,
        'restrictedusers' => 1,
        'downloadfiles' => 1,
        'uploadfiles' => 0,
    ],
];
