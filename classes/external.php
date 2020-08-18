<?php
/**
 * External.
 *
 * @package mod_poodlltime
 * @author  Justin Hunt - poodll.com
 */


use mod_poodlltime\utils;
use mod_poodlltime\constants;
use mod_poodlltime\diff;

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
        $ret= utils::update_step_grade($cmid,$grade, $step);
        return true;
    }
    public static function report_step_grade_returns() {
        return new external_value(PARAM_BOOL);
    }


    public static function compare_passage_to_transcript_parameters(){
        return new external_function_parameters(
                array('transcript' => new external_value(PARAM_TEXT, 'The spoken phrase'),
                        'passage' => new external_value(PARAM_TEXT, 'The correct phrase'),
                        'language' => new external_value(PARAM_TEXT, 'The language eg en-US'),
                        'alternatives' => new external_value(PARAM_TEXT, 'list of alternatives',false,'')
                )
        );

    }

    public static function compare_passage_to_transcript($transcript,$passage,$language,$alternatives) {
        global $DB;

        //turn the passage and transcript into an array of words
        $passagebits = diff::fetchWordArray($passage);
        $alternatives = diff::fetchAlternativesArray($alternatives);
        $transcriptbits = diff::fetchWordArray($transcript);
        $wildcards = diff::fetchWildcardsArray($alternatives);

        //fetch sequences of transcript/passage matched words
        // then prepare an array of "differences"
        $passagecount = count($passagebits);
        $transcriptcount = count($transcriptbits);
        $sequences = diff::fetchSequences($passagebits, $transcriptbits, $alternatives, $language);
        //fetch diffs
        $debug=false;
        $diffs = diff::fetchDiffs($sequences, $passagecount, $transcriptcount, $debug);
        $diffs = diff::applyWildcards($diffs, $passagebits, $wildcards);


        //from the array of differences build error data, match data, markers, scores and metrics
        $errors = new \stdClass();
        $currentword = 0;

        //loop through diffs
        $results=[];
        foreach ($diffs as $diff) {
            $currentword++;
            $result = new \stdClass();
            $result->word = $passagebits[$currentword - 1];
            $result->wordnumber = $currentword;
            switch ($diff[0]) {
                case Diff::UNMATCHED:
                    //we collect error info so we can count and display them on passage

                    $result->matched =false;
                    break;

                case Diff::MATCHED:
                    $result->matched =true;
                    break;

                default:
                    //do nothing
                    //should never get here
            }
            $results[]=$result;
        }

        //finalise and serialise session errors
        $sessionresults = json_encode($results);

        return $sessionresults;

    }
    public static function compare_passage_to_transcript_returns() {
        return new external_value(PARAM_RAW);
    }


    public static function submit_mform_parameters() {
        return new external_function_parameters(
                array(
                        'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                        'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
                        'formname' => new external_value(PARAM_TEXT, 'The formname')
                )
        );
    }

    public static function submit_mform($contextid,$jsonformdata, $formname) {
        global $CFG, $DB, $USER;


        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_mform_parameters(),
                ['contextid' => $contextid, 'jsonformdata' => $jsonformdata, 'formname'=>$formname]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

        //Init return object
        $ret = new \stdClass();
        $ret->itemid=0;
        $ret->error=true;
        $ret->message="";


        list($ignored, $course) = get_context_info_array($context->id);
        $serialiseddata = json_decode($params['jsonformdata']);

        $data = array();
        parse_str($serialiseddata, $data);

        //get filechooser and html editor options
        $editoroptions = \mod_poodlltime\rsquestion\helper::fetch_editor_options($course, $context);
        $filemanageroptions = \mod_poodlltime\rsquestion\helper::fetch_filemanager_options($course,1);

        // get the objects we need
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);


        //we need to pretend this was posted and these help
        $method='post';
        $target='';
        $attributes=null;
        $editable=true;

        //get the mform for our item
        switch($formname){


            case constants::TYPE_MULTICHOICE:
                $mform = new \mod_poodlltime\rsquestion\multichoiceform(null,
                        array('editoroptions'=>$editoroptions,
                                'filemanageroptions'=>$filemanageroptions,
                                'moduleinstance'=>$moduleinstance),
                        $method, $target,$attributes,$editable,$data
                );
                break;

            case constants::TYPE_DICTATIONCHAT:
                $mform = new \mod_poodlltime\rsquestion\dictationchatform(null,
                        array('editoroptions'=>$editoroptions,
                                'filemanageroptions'=>$filemanageroptions,
                                'moduleinstance'=>$moduleinstance),
                        $method, $target,$attributes,$editable,$data
                );
                break;

            case constants::TYPE_DICTATION:
                $mform = new \mod_poodlltime\rsquestion\dictationform(null,
                        array('editoroptions'=>$editoroptions,
                                'filemanageroptions'=>$filemanageroptions,
                                'moduleinstance'=>$moduleinstance),
                        $method, $target,$attributes,$editable,$data
                );
                break;

            case constants::TYPE_SPEECHCARDS:
                $mform = new \mod_poodlltime\rsquestion\speechcardsform(null,
                        array('editoroptions'=>$editoroptions,
                                'filemanageroptions'=>$filemanageroptions,
                                'moduleinstance'=>$moduleinstance),
                        $method, $target,$attributes,$editable,$data
                );
                break;

            case constants::TYPE_LISTENREPEAT:
                $mform = new \mod_poodlltime\rsquestion\listenrepeatform(null,
                        array('editoroptions'=>$editoroptions,
                                'filemanageroptions'=>$filemanageroptions,
                                'moduleinstance'=>$moduleinstance),
                        $method, $target,$attributes,$editable,$data
                );
                break;

            case constants::TYPE_PAGE:
                $mform = new \mod_poodlltime\rsquestion\pageform(null,
                        array('editoroptions'=>$editoroptions,
                                'filemanageroptions'=>$filemanageroptions,
                                'moduleinstance'=>$moduleinstance),
                        $method, $target,$attributes,$editable,$data
                );
                break;

            case constants::NONE:
            default:
                print_error('No item type specifified');
                return 0;

        }

        $validateddata = $mform->get_data();
        if ($validateddata) {
            $edit=$validateddata->id ? true : false;
            //currently data is an array, but it should be an object
            $data = (object)$data;
            $data->type = $formname;
            $result = \mod_poodlltime\rsquestion\helper::update_insert_question($moduleinstance,$data,$edit,$context,$cm,$editoroptions,$filemanageroptions);
            if($result->error==true){
                    $ret->message = $result->message;
                }else{
                    $theitem=$result->item;
                    $ret->itemid=$theitem->id;
                    $ret->error=false;
                }
            }
            return json_encode($ret);
    }

    public static function submit_mform_returns() {
        return new external_value(PARAM_RAW);
        //return new external_value(PARAM_INT, 'group id');
    }

    public static function delete_item_parameters() {
        return new external_function_parameters(
                array(
                        'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                        'itemid' => new external_value(PARAM_INT, 'The itemid to delete'),
                        'formname' => new external_value(PARAM_TEXT, 'The formname')
                )
        );
    }

    public static function delete_item($contextid,$itemid, $formname)
    {
        global $CFG, $DB, $USER;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::delete_item_parameters(),
                ['contextid' => $contextid, 'itemid' => $itemid, 'formname' => $formname]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

       //DO DELETE
        // get the objects we need
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);
        $success = \mod_poodlltime\rsquestion\helper::delete_item($moduleinstance,$itemid,$context);

        $ret = new \stdClass();
        $ret->itemid=$itemid;
        $ret->error=false;
        return json_encode($ret);
    }

    public static function delete_item_returns() {
        return new external_value(PARAM_RAW);
        //return new external_value(PARAM_INT, 'group id');
    }

    public static function move_item_parameters() {
        return new external_function_parameters(
                array(
                        'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                        'itemid' => new external_value(PARAM_INT, 'The itemid to move'),
                        'direction' => new external_value(PARAM_TEXT, 'The move direction')
                )
        );
    }

    public static function move_item($contextid,$itemid, $direction)
    {
        global $CFG, $DB, $USER;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::move_item_parameters(),
                ['contextid' => $contextid, 'itemid' => $itemid, 'direction' => $direction]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

        //DO move
        // get the objects we need
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);
        \mod_poodlltime\rsquestion\helper::move_item($moduleinstance,$itemid,$direction);

        $ret = new \stdClass();
        $ret->itemid=$itemid;
        $ret->error=false;
        return json_encode($ret);
    }

    public static function move_item_returns() {
        return new external_value(PARAM_RAW);
    }




}
