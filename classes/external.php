<?php
/**
 * External.
 *
 * @package mod_poodlltime
 * @author  Justin Hunt - poodll.com
 */


use mod_poodlltime\utils;
use mod_poodlltime\constants;

/**
 * External class.
 *
 * @package mod_poodlltime
 * @author  Justin Hunt - poodll.com
 */
class mod_poodlltime_external extends external_api {

    public static function check_by_phonetic_parameters(){
        return new external_function_parameters(
                 array('spoken' => new external_value(PARAM_TEXT, 'The spoken phrase'),
                       'correct' => new external_value(PARAM_TEXT, 'The correct phrase'),
                       'language' => new external_value(PARAM_TEXT, 'The language eg en-US')
                 )
        );

    }
    public static function check_by_phonetic($spoken, $correct, $language){
        $language = substr($language,0,2);
        $spokenphonetic = utils::convert_to_phonetic($spoken,$language);
        $correctphonetic = utils::convert_to_phonetic($correct,$language);
        $similar_percent = 0;
        $similar_chars = similar_text($correctphonetic,$spokenphonetic,$similar_percent);
        return round($similar_percent,0);

    }

    public static function check_by_phonetic_returns(){
        return new external_value(PARAM_INT,'how close is spoken to correct, 0 - 100');
    }


    public static function report_step_grade_parameters() {
        return new external_function_parameters([
                'cmid' => new external_value(PARAM_INT),
                'grade' => new external_value(PARAM_INT),
                'step' => new external_value(PARAM_INT)
        ]);
    }

    public static function report_step_grade($cmid,$grade,$step){
       // $ret= utils::update_step_grade($modid, $correct);
        return true;
    }
    public static function report_step_grade_returns() {
        return new external_value(PARAM_BOOL);
    }

}
