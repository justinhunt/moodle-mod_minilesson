<?php

/**
 * Services definition.
 *
 * @package mod_minilesson
 * @author  Justin Hunt - poodll.com
 */

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
        'capabilities'=> 'mod/minilesson:view',
        'type'        => 'write',
        'ajax'        => true,
    ),
];
