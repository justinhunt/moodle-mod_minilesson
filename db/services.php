<?php
/**
 * Services definition.
 *
 * @package mod_poodlltime
 * @author  Justin Hunt - poodll.com
 */

$functions = array(

        'mod_poodlltime_report_step_grade' => array(
                'classname'   => 'mod_poodlltime_external',
                'methodname'  => 'report_step_grade',
                'description' => 'Reports the grade of a step',
                'capabilities'=> 'mod/poodlltime:view',
                'type'        => 'write',
                'ajax'        => true,
        ),

        'mod_poodlltime_check_by_phonetic' => array(
                'classname'   => 'mod_poodlltime_external',
                'methodname'  => 'check_by_phonetic',
                'description' => 'compares a spoken phrase to a correct phrase by phoneme' ,
                'capabilities'=> 'mod/poodlltime:view',
                'type'        => 'read',
                'ajax'        => true,
        ),
        'mod_poodlltime_compare_passage_to_transcript' => array(
        'classname'   => 'mod_poodlltime_external',
        'methodname'  => 'compare_passage_to_transcript',
        'description' => 'compares a spoken phrase to a correct phrase' ,
        'capabilities'=> 'mod/poodlltime:view',
        'type'        => 'read',
        'ajax'        => true,
        ),

        'mod_poodlltime_submit_mform' => array(
                'classname'   => 'mod_poodlltime_external',
                'methodname'  => 'submit_mform',
                'description' => 'submits mform.',
                'capabilities'=> 'mod/poodlltime:managequestions',
                'type'        => 'write',
                'ajax'        => true,
        ),
);