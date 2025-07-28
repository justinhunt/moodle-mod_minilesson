<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External.
 *
 * @package mod_minilesson
 * @author  Justin Hunt - poodll.com
 */


global $CFG;
// This is for pre M4.0 and post M4.0 to work on same code base
require_once($CFG->libdir . '/externallib.php');

/*
 * This is for M4.0 and later
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
*/

use mod_minilesson\utils;
use mod_minilesson\constants;
use mod_minilesson\diff;
use mod_minilesson\alphabetconverter;
use mod_minilesson\local\itemtype\item;

/**
 * External class.
 *
 * @package mod_minilesson
 * @author  Justin Hunt - poodll.com
 */
class mod_minilesson_external extends external_api {

    public static function create_instance_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course id', VALUE_REQUIRED),
            'moduledata' => new external_value(PARAM_TEXT, 'The module data in JSON format', VALUE_REQUIRED),
        ]);
    }

    public static function create_instance($courseid, $moduledata) {
        global $DB, $USER;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $config = get_config(constants::M_COMPONENT);

        // get our default data
        $data = new \stdClass();
        $data->modulename = "minilesson";
        $data->name = "This is an auto generated minilesson";
        $data->intro = '';
        $data->pagelayout = 'standard';
        // $data->timelimit=0;
        $data->showqtitles = 0;
        $data->maxattempts = 0;
        $data->ttslanguage = $config->ttslanguage;
        $data->region = $config->awsregion;
        $data->transcriber = $config->transcriber;
        $data->richtextprompt = $config->prompttype;
        $data->containerwidth = $config->containerwidth;
        $data->activitylink = 0;
        $data->foriframe = 0;
        $data->grade = 0;
        $data->visible = 1;

        // write over the default data with any passed in data that we have
        if(utils::is_json($moduledata)){
            $moduledata = json_decode($moduledata);
            foreach ($moduledata as $property => $value) {
                $data->{$property} = $value;
            }
        }

        // create instance code goes here
        $cmid = utils::create_instance($data, $course);
        return ['cmid' => $cmid];
    }

    public static function create_instance_returns() {
        return new external_single_structure(
            [
                'cmid' => new external_value(PARAM_INT, 'cmid of new instance'),
            ]
        );
    }

    public static function check_by_phonetic_parameters() {
        return new external_function_parameters(
                 ['spoken' => new external_value(PARAM_TEXT, 'The spoken phrase'),
                       'correct' => new external_value(PARAM_TEXT, 'The correct phrase'),
                       'phonetic' => new external_value(PARAM_TEXT, 'The correct phonetic'),
                       'language' => new external_value(PARAM_TEXT, 'The language eg en-US'),
                       'region' => new external_value(PARAM_TEXT, 'The region'),
                       'cmid' => new external_value(PARAM_INT, 'The cmid'),
                 ]
        );

    }
    public static function check_by_phonetic($spoken, $correct, $phonetic, $language, $region, $cmid) {
        $segmented = true;
        $shortlang = utils::fetch_short_lang($language);
        switch($language){
            case constants::M_LANG_JAJP:

                // find digits in original passage, and convert number words to digits in the target passage
                // this works but segmented digits are a bit messed up, not sure its worthwhile. more testing needed
                $spoken = alphabetconverter::words_to_suji_convert($phonetic, $spoken);
                break;
            case constants::M_LANG_ENUS:
            case constants::M_LANG_ENAB:
            case constants::M_LANG_ENAU:
            case constants::M_LANG_ENGB:
            case constants::M_LANG_ENIE:
            case constants::M_LANG_ENIN:
            case constants::M_LANG_ENNZ:
            case constants::M_LANG_ENWL:
            case constants::M_LANG_ENZA:
                // find digits in original passage, and convert number words to digits in the target passage
                $spoken = alphabetconverter::words_to_numbers_convert($correct, $spoken, $shortlang);
                break;
            case constants::M_LANG_DEDE:
            case constants::M_LANG_DECH:
                // find eszetts in original passage, and convert ss words to eszetts in the target passage
                $spoken = alphabetconverter::ss_to_eszett_convert($correct, $spoken);
                break;
        }
        list($spokenphonetic) = utils::fetch_phones_and_segments($spoken, $language, $region, $segmented);
        $similarpercent = 0;

        // if our convert_to_phonetic returned false(error) then its hopeless, return 0
        if($spokenphonetic === false){
            return 0;
        }

        // if one of our phonetics is just empty, it is also hopeless
        if(empty($spokenphonetic) || empty($phonetic)){
            return 0;
        }

        // similar_percent calc'd by reference but multibyte is weird
        if($language !== constants::M_LANG_JAJP) {
            similar_text($phonetic, $spokenphonetic, $similarpercent);
        }else{
            $similarpercent = $phonetic == $spokenphonetic ? 100 : 0;
        }
        return round($similarpercent, 0);

    }

    public static function check_by_phonetic_returns() {
        return new external_value(PARAM_INT, 'how close is spoken to correct, 0 - 100');
    }


    public static function report_step_grade_parameters() {
        return new external_function_parameters([
                'cmid' => new external_value(PARAM_INT),
                'step' => new external_value(PARAM_RAW),
        ]);
    }

    public static function report_step_grade($cmid, $step) {
        $stepdata = json_decode($step);
        list($success, $message, $returndata) = utils::update_step_grade($cmid, $stepdata);
        return $success;
    }
    public static function report_step_grade_returns() {
        return new external_value(PARAM_BOOL);
    }


    public static function compare_passage_to_transcript_parameters() {
        return new external_function_parameters(
                ['transcript' => new external_value(PARAM_TEXT, 'The spoken phrase', VALUE_REQUIRED),
                        'passage' => new external_value(PARAM_TEXT, 'The correct phrase', VALUE_REQUIRED),
                        'language' => new external_value(PARAM_TEXT, 'The language eg en-US', VALUE_REQUIRED),
                        'alternatives' => new external_value(PARAM_TEXT, 'list of alternatives', VALUE_DEFAULT, ''),
                        'phonetic' => new external_value(PARAM_TEXT, 'phonetic reading', VALUE_DEFAULT, ''),
                        'region' => new external_value(PARAM_TEXT, 'The region', VALUE_DEFAULT, 'tokyo'),
                        'cmid' => new external_value(PARAM_INT, 'The cmid'),
                ]
        );

    }

    public static function compare_passage_to_transcript($transcript, $passage, $language, $alternatives, $phonetic, $region, $cmid) {
        global $DB;

        // Fetch phonetics and segments
        list($transcriptphonetic, $transcript) = utils::fetch_phones_and_segments($transcript, $language, $region);

        // EXPERIMENTAL
        $shortlang = utils::fetch_short_lang($language);
        switch ($shortlang){

            case 'de':
                $transcript = alphabetconverter::words_to_numbers_convert($passage, $transcript, $shortlang);
                // find eszetts in original passage, and convert ss words to eszetts in the target passage (transcript)
                $transcript = alphabetconverter::ss_to_eszett_convert($passage, $transcript );

                break;
            case 'ja':
                // find digits in original passage, and convert number words to digits in the target passage
                // this works but segmented digits are a bit messed up, not sure its worthwhile. more testing needed
                // from here and aigrade
                $transcript = alphabetconverter::words_to_suji_convert($passage, $transcript);
                break;
            case 'en':
            default:
                // find digits in original passage, and convert number words to digits in the target passage (transcript)
                $transcript = alphabetconverter::words_to_numbers_convert($passage, $transcript, $shortlang);

                break;
        }

        // we also want to fetch the alternatives for the number_words in passage (though we expect number_digits there)
        $alternatives .= PHP_EOL . alphabetconverter::fetch_numerical_alternates($shortlang);  // "four|for|4";

        // If this is Japanese, and the passage has been segmented, we want to segment it into "words"
        /*
        if($language == constants::M_LANG_JAJP) {
            $transcript = utils::segment_japanese($transcript);
            $passage = utils::segment_japanese($passage);
            $segmented=true;
            $transcript_phonetic = utils::convert_to_phonetic($transcript,constants::M_LANG_JAJP,$region,$segmented);
        }else{
            $transcript_phonetic ='';
        }
        */

        // turn the passage and transcript into an array of words
        $passagebits = diff::fetchWordArray($passage);

        $alternatives = diff::fetchAlternativesArray($alternatives);
        $transcriptbits = diff::fetchWordArray($transcript);
        $transcriptphoneticbits = diff::fetchWordArray($transcriptphonetic);
        $passagephoneticbits = diff::fetchWordArray($phonetic);
        $wildcards = diff::fetchWildcardsArray($alternatives);

        // fetch sequences of transcript/passage matched words
        // then prepare an array of "differences"
        $passagecount = count($passagebits);
        $transcriptcount = count($transcriptbits);
        $sequences = diff::fetchSequences($passagebits, $transcriptbits, $alternatives, $language,
                $transcriptphoneticbits , $passagephoneticbits);
        // fetch diffs
        $debug = false;
        $diffs = diff::fetchDiffs($sequences, $passagecount, $transcriptcount, $debug);
        $diffs = diff::applyWildcards($diffs, $passagebits, $wildcards);

        // from the array of differences build error data, match data, markers, scores and metrics
        $errors = new \stdClass();
        $currentword = 0;

        // loop through diffs
        $results = [];
        foreach ($diffs as $diff) {
            $currentword++;
            $result = new \stdClass();
            $result->word = $passagebits[$currentword - 1];
            $result->wordnumber = $currentword;
            switch ($diff[0]) {
                case Diff::UNMATCHED:
                    // we collect error info so we can count and display them on passage

                    $result->matched = false;
                    break;

                case Diff::MATCHED:
                    $result->matched = true;
                    break;

                default:
                    // do nothing
                    // should never get here
            }
            $results[] = $result;
        }

        // finalise and serialise session errors
        $sessionresults = json_encode($results);

        return $sessionresults;

    }
    public static function compare_passage_to_transcript_returns() {
        return new external_value(PARAM_RAW);
    }

    public static function evaluate_transcript_parameters() {
        return new external_function_parameters(
                ['transcript' => new external_value(PARAM_TEXT, 'The transcript of speaking or writing', VALUE_REQUIRED),
                        'itemid' => new external_value(PARAM_INT, 'The item id in the minilesson', VALUE_REQUIRED),
                        'cmid' => new external_value(PARAM_INT, 'The cmid', VALUE_REQUIRED),
                ]
        );
    }

    public static function evaluate_transcript($transcript, $itemid, $cmid) {
        global $DB;
        $ret = utils::evaluate_transcript($transcript, $itemid, $cmid);
        return json_encode($ret);
    }

    public static function evaluate_transcript_returns() {
        return new external_value(PARAM_RAW);
    }

    public static function submit_mform_parameters() {
        return new external_function_parameters(
                [
                        'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                        'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
                        'formname' => new external_value(PARAM_TEXT, 'The formname'),
                ]
        );
    }

    public static function submit_mform($contextid, $jsonformdata, $formname) {
        global $CFG, $DB, $USER;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_mform_parameters(),
                ['contextid' => $contextid, 'jsonformdata' => $jsonformdata, 'formname' => $formname]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

        // Init return object
        $ret = new \stdClass();
        $ret->itemid = 0;
        $ret->error = true;
        $ret->message = "";

        list($ignored, $course) = get_context_info_array($context->id);
        $serialiseddata = json_decode($params['jsonformdata']);

        $data = [];
        parse_str($serialiseddata, $data);

        // get filechooser and html editor options
        $editoroptions = \mod_minilesson\local\itemtype\item::fetch_editor_options($course, $context);
        $filemanageroptions = \mod_minilesson\local\itemtype\item::fetch_filemanager_options($course, 3);

        // get the objects we need
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);

        // we need to pretend this was posted and these help
        $method = 'post';
        $target = '';
        $attributes = null;
        $editable = true;

        $itemformclass  = utils::fetch_itemform_classname($formname);
        if(!$itemformclass){
                print_error('No item type specifified');
                return 0;
        }
        $mform = new $itemformclass(null,
                        ['editoroptions' => $editoroptions,
                                'filemanageroptions' => $filemanageroptions,
                                'moduleinstance' => $moduleinstance],
                        $method, $target, $attributes, $editable, $data
                );

        $validateddata = $mform->get_data();
        if ($validateddata) {
            $edit = $validateddata->itemid ? true : false;
            // currently data is an array, but it should be an object
            $vdata = (object)$validateddata;
            $vdata->type = $formname;

            // update or add
            if($edit){
                $theitem = utils::fetch_item_from_itemrecord($vdata, $moduleinstance); // $DB->get_record(constants::M_QTABLE, array('id'=>$data->itemid,constants::M_MODNAME => $cm->instance));
                $olditem = $DB->get_record(constants::M_QTABLE, ['id' => $vdata->itemid, constants::M_MODNAME => $cm->instance]);
            }else{
                $theitem = utils::fetch_item_from_itemrecord($vdata, $moduleinstance);
                $olditem = false;
            }

            // remove bad accents and things that mess up transcription (kind of like clear but permanent)
            $theitem->deaccent();

            // get passage hash
            $theitem->update_create_langmodel($olditem);

            // lets update the phonetics
            $theitem->update_create_phonetic($olditem);

            $result = $theitem->update_insert_item();
            if($result->error == true){
                    $ret->message = $result->message;
            }else{
                $theitem = $result->item;
                $ret->itemid = $theitem->id;
                $ret->error = false;
            }
        }
            return json_encode($ret);
    }

    public static function submit_mform_returns() {
        return new external_value(PARAM_RAW);
        // return new external_value(PARAM_INT, 'group id');
    }

    public static function delete_item_parameters() {
        return new external_function_parameters(
                [
                        'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                        'itemid' => new external_value(PARAM_INT, 'The itemid to delete'),
                        'formname' => new external_value(PARAM_TEXT, 'The formname'),
                ]
        );
    }

    public static function delete_item($contextid, $itemid, $formname) {
        global $CFG, $DB, $USER;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::delete_item_parameters(),
                ['contextid' => $contextid, 'itemid' => $itemid, 'formname' => $formname]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

        // DO DELETE
        // get the objects we need
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $success = \mod_minilesson\local\itemtype\item::delete_item($itemid, $context);

        $ret = new \stdClass();
        $ret->itemid = $itemid;
        $ret->error = false;
        return json_encode($ret);
    }

    public static function delete_item_returns() {
        return new external_value(PARAM_RAW);
        // return new external_value(PARAM_INT, 'group id');
    }

    public static function move_item_parameters() {
        return new external_function_parameters(
                [
                        'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                        'itemid' => new external_value(PARAM_INT, 'The itemid to move'),
                        'direction' => new external_value(PARAM_TEXT, 'The move direction'),
                ]
        );
    }

    public static function move_item($contextid, $itemid, $direction) {
        global $CFG, $DB, $USER;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::move_item_parameters(),
                ['contextid' => $contextid, 'itemid' => $itemid, 'direction' => $direction]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

        // DO move
        // get the objects we need
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        \mod_minilesson\local\itemform\helper::move_item($moduleinstance, $itemid, $direction);

        $ret = new \stdClass();
        $ret->itemid = $itemid;
        $ret->error = false;
        return json_encode($ret);
    }

    public static function move_item_returns() {
        return new external_value(PARAM_RAW);
    }

    public static function duplicate_item_parameters() {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                'itemid' => new external_value(PARAM_INT, 'The itemid to move'),
            ]
        );
    }

    public static function duplicate_item($contextid, $itemid) {
        global $CFG, $DB, $USER;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::duplicate_item_parameters(),
            ['contextid' => $contextid, 'itemid' => $itemid]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

        // DO move
        // get the objects we need
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        list($newitemid, $newitemname, $type, $typelabel) = \mod_minilesson\local\itemform\helper::duplicate_item($moduleinstance, $context, $itemid);

        $ret = new \stdClass();
        $ret->olditemid = $itemid;
        $ret->newitemid = $newitemid;
        $ret->newitemname = $newitemname;
        $ret->type = $type;
        $ret->typelabel = $typelabel;
        $ret->error = false;
        return json_encode($ret);
    }

    public static function duplicate_item_returns() {
        return new external_value(PARAM_RAW);
    }


    public static function check_grammar($text, $language) {
        global $DB, $USER;

        $params = self::validate_parameters(self::check_grammar_parameters(), [
            'text' => $text,
            'language' => $language]);
        extract($params);

        $siteconfig = get_config(constants::M_COMPONENT);
        $region = $siteconfig->awsregion;
        $token = utils::fetch_token($siteconfig->apiuser, $siteconfig->apisecret);
        $textanalyser = new textanalyser($token, $text, $region, $language);
        $suggestions = $textanalyser->fetch_grammar_correction();
        if ($suggestions == $text || empty($suggestions)) {
            return "";
        }

        // if we have suggestions, mark those up and return them
        $direction = "r2l"; // "l2r";
        list($grammarerrors, $grammarmatches, $insertioncount) = utils::fetch_grammar_correction_diff($text, $suggestions, $direction);
        $markedupsuggestions = \mod_minilesson\aitranscriptutils::render_passage($suggestions, 'corrections');
        $ret = [];
        $ret['grammarerrors'] = $grammarerrors;
        $ret['grammarmatches'] = $grammarmatches;
        $ret['suggestions'] = $suggestions;
        $ret['markedupsuggestions'] = $markedupsuggestions;
        $ret['insertioncount'] = $insertioncount;

        return json_encode($ret);

    }

    public static function check_grammar_parameters() {
        return new external_function_parameters([
            'text' => new external_value(PARAM_TEXT),
            'language' => new external_value(PARAM_TEXT),
        ]);
    }

    public static function check_grammar_returns() {
        return new external_value(PARAM_RAW);
    }

    public static function refresh_token($type, $region) {
        global $DB, $USER;
        $fulltoken = false;
        $params = self::validate_parameters(self::refresh_token_parameters(), [
            'type' => $type,
            'region' => $region]);
        extract($params);

        // Do the token refresh.
        switch($type){
            case 'cloudpoodll':
                $siteconfig = get_config(constants::M_COMPONENT);
                $region = $siteconfig->awsregion;
                // We fetch the token (just the key) to update cache if needed.
                $token = utils::fetch_token($siteconfig->apiuser, $siteconfig->apisecret);
                // We fetch the full token from the cache, it will have "validuntil" set.
                $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
                $fulltoken = $cache->get('recentpoodlltoken');
                break;

            case 'msspeech':
                $fulltoken = utils::fetch_msspeech_token($region);
                break;

            case 'streaming':
                $fulltoken = utils::fetch_streaming_token($region);
                break;

            case 'openai':
                $fulltoken = utils::fetch_openai_token($region);
                break;
            default:
                throw new \moodle_exception('invalidtype', constants::M_COMPONENT);
        }
        return json_encode($fulltoken);
    }

    public static function refresh_token_parameters() {
        return new external_function_parameters([
            'type' => new external_value(PARAM_TEXT),
            'region' => new external_value(PARAM_TEXT),
        ]);
    }

    public static function refresh_token_returns() {
        return new external_value(PARAM_RAW);
    }

}
