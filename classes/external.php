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
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
// This is for pre M4.0 and post M4.0 to work on same code base.
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
use mod_minilesson\curl;

/**
 * External class.
 *
 * @package mod_minilesson
 * @author  Justin Hunt - poodll.com
 */
class mod_minilesson_external extends external_api
{
    /**
     * create new instance parameters
     * @return external_function_parameters
     */
    public static function create_instance_parameters()
    {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course id', VALUE_REQUIRED),
            'moduledata' => new external_value(PARAM_TEXT, 'The module data in JSON format', VALUE_REQUIRED),
        ]);
    }

    /**
     * Create new instance
     * @param int $courseid
     * @param string $moduledata
     * @return array
     */
    public static function create_instance($courseid, $moduledata)
    {
        global $DB, $USER;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $config = get_config(constants::M_COMPONENT);

        // Get our default data.
        $data = new \stdClass();
        $data->modulename = "minilesson";
        $data->name = "This is an auto generated minilesson";
        $data->intro = '';
        $data->pagelayout = 'standard';
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

        // Write over the default data with any passed in data that we have.
        if (utils::is_json($moduledata)) {
            $moduledata = json_decode($moduledata);
            foreach ($moduledata as $property => $value) {
                $data->{$property} = $value;
            }
        }

        // Create instance code goes here.
        $cmid = utils::create_instance($data, $course);
        return ['cmid' => $cmid];
    }

    /**
     * Create new instance returns
     * @return external_single_structure
     */
    public static function create_instance_returns()
    {
        return new external_single_structure(
            [
                'cmid' => new external_value(PARAM_INT, 'cmid of new instance'),
            ]
        );
    }

    /**
     * check phonetic parameters
     * @return external_function_parameters
     */
    public static function check_by_phonetic_parameters()
    {
        return new external_function_parameters(
            [
                'spoken' => new external_value(PARAM_TEXT, 'The spoken phrase'),
                'correct' => new external_value(PARAM_TEXT, 'The correct phrase'),
                'phonetic' => new external_value(PARAM_TEXT, 'The correct phonetic'),
                'language' => new external_value(PARAM_TEXT, 'The language eg en-US'),
                'region' => new external_value(PARAM_TEXT, 'The region'),
                'cmid' => new external_value(PARAM_INT, 'The cmid'),
            ]
        );
    }

    /**
     * Check by phonetic
     * @param string $spoken
     * @param string $correct
     * @param string $phonetic
     * @param string $language
     * @param string $region
     * @param int $cmid
     * @return float|int
     */
    public static function check_by_phonetic($spoken, $correct, $phonetic, $language, $region, $cmid)
    {
        $segmented = true;
        $shortlang = utils::fetch_short_lang($language);
        switch ($language) {
            case constants::M_LANG_JAJP:
                // Find digits in original passage, and convert number words to digits in the target passage.
                // This works but segmented digits are a bit messed up, not sure its worthwhile. more testing needed.
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
                // Find digits in original passage, and convert number words to digits in the target passage.
                $spoken = alphabetconverter::words_to_numbers_convert($correct, $spoken, $shortlang);
                break;
            case constants::M_LANG_DEDE:
            case constants::M_LANG_DECH:
                // Find eszetts in original passage, and convert ss words to eszetts in the target passage.
                $spoken = alphabetconverter::ss_to_eszett_convert($correct, $spoken);
                break;
        }
        [$spokenphonetic] = utils::fetch_phones_and_segments($spoken, $language, $region, $segmented);
        $similarpercent = 0;

        // If our convert_to_phonetic returned false(error) then its hopeless, return 0.
        if ($spokenphonetic === false) {
            return 0;
        }

        // If one of our phonetics is just empty, it is also hopeless.
        if (empty($spokenphonetic) || empty($phonetic)) {
            return 0;
        }

        // Similar_percent calc'd by reference but multibyte is weird.
        if ($language !== constants::M_LANG_JAJP) {
            similar_text($phonetic, $spokenphonetic, $similarpercent);
        } else {
            $similarpercent = $phonetic == $spokenphonetic ? 100 : 0;
        }
        return round($similarpercent, 0);
    }

    /**
     * check phonetic returns
     * @return external_value
     */
    public static function check_by_phonetic_returns()
    {
        return new external_value(PARAM_INT, 'how close is spoken to correct, 0 - 100');
    }

    /**
     * step grade report parameters
     * @return external_function_parameters
     */
    public static function report_step_grade_parameters()
    {
        return new external_function_parameters([
                'cmid' => new external_value(PARAM_INT),
                'step' => new external_value(PARAM_RAW),
        ]);
    }

    /**
     * report step grade
     * @param int $cmid
     * @param mixed $step
     * @return bool|string
     */
    public static function report_step_grade($cmid, $step)
    {
        $stepdata = json_decode($step);
        [$success, $message, $returndata] = utils::update_step_grade($cmid, $stepdata);
        return $success;
    }
    /**
     * report step grade returns
     * @return external_value
     */
    public static function report_step_grade_returns()
    {
        return new external_value(PARAM_BOOL);
    }


    /**
     * compare passage to transcript parameters
     * @return external_function_parameters
     */
    public static function compare_passage_to_transcript_parameters()
    {
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

    /**
     * compare passage to transcript
     * @param string $transcript
     * @param string $passage
     * @param string $language
     * @param string $alternatives
     * @param string $phonetic
     * @param string $region
     * @param int $cmid
     * @return string
     */
    public static function compare_passage_to_transcript(
        $transcript,
        $passage,
        $language,
        $alternatives,
        $phonetic,
        $region,
        $cmid
    ) {
        global $DB;

        // Fetch phonetics and segments.
        [$transcriptphonetic, $transcript] = utils::fetch_phones_and_segments($transcript, $language, $region);

        // EXPERIMENTAL.
        $shortlang = utils::fetch_short_lang($language);
        switch ($shortlang) {
            case 'de':
                $transcript = alphabetconverter::words_to_numbers_convert($passage, $transcript, $shortlang);
                // Find eszetts in original passage, and convert ss words to eszetts in the target passage (transcript).
                $transcript = alphabetconverter::ss_to_eszett_convert($passage, $transcript);

                break;
            case 'ja':
                // Find digits in original passage, and convert number words to digits in the target passage
                // this works but segmented digits are a bit messed up, not sure its worthwhile. more testing needed
                // from here and aigrade.
                $transcript = alphabetconverter::words_to_suji_convert($passage, $transcript);
                break;
            case 'en':
            default:
                // Find digits in original passage, and convert number words to digits in the target passage (transcript).
                $transcript = alphabetconverter::words_to_numbers_convert($passage, $transcript, $shortlang);

                break;
        }

        // We also want to fetch the alternatives for the number_words in passage (though we expect number_digits there).
        $alternatives .= PHP_EOL . alphabetconverter::fetch_numerical_alternates($shortlang);  // Four|for|4".

        // If this is Japanese, we want to segment it into "words"
        // Actually in most cases it will be, but speaking gap fill is an outlier, it sends just the words
        // and they are not segmented because it is very hard to do that with the processing needed to make gaps
        // and the phonetic will be the full sentence phonetic so here we get just the words phonetic
        // So ... we need to segment here just in case its from speaking gap fill. To Do.
        if ($language == constants::M_LANG_JAJP) {
            [$phonetic, $passage] = utils::fetch_phones_and_segments($passage, $language, $region);
        }

        // Turn the passage and transcript into an array of words.
        $passagebits = diff::fetchWordArray($passage);
        $alternatives = diff::fetchAlternativesArray($alternatives);
        $transcriptbits = diff::fetchWordArray($transcript);
        $transcriptphoneticbits = diff::fetchWordArray($transcriptphonetic);
        $passagephoneticbits = diff::fetchWordArray($phonetic);
        $wildcards = diff::fetchWildcardsArray($alternatives);

        // Fetch sequences of transcript/passage matched words
        // Then prepare an array of "differences".
        $passagecount = count($passagebits);
        $transcriptcount = count($transcriptbits);
        $sequences = diff::fetchSequences(
            $passagebits,
            $transcriptbits,
            $alternatives,
            $language,
            $transcriptphoneticbits,
            $passagephoneticbits
        );
        // Fetch diffs.
        $debug = false;
        $diffs = diff::fetchDiffs($sequences, $passagecount, $transcriptcount, $debug);
        $diffs = diff::applyWildcards($diffs, $passagebits, $wildcards);

        // From the array of differences build error data, match data, markers, scores and metrics.
        $errors = new \stdClass();
        $currentword = 0;

        // Loop through diffs.
        $results = [];
        foreach ($diffs as $diff) {
            $currentword++;
            $result = new \stdClass();
            $result->word = $passagebits[$currentword - 1];
            $result->wordnumber = $currentword;
            switch ($diff[0]) {
                case Diff::UNMATCHED:
                    // We collect error info so we can count and display them on passage.

                    $result->matched = false;
                    break;

                case Diff::MATCHED:
                    $result->matched = true;
                    break;

                default:
                    // Do nothing
                    // Should never get here.
            }
            $results[] = $result;
        }

        // Finalise and serialise session errors.
        $sessionresults = json_encode($results);

        return $sessionresults;
    }

    /**
     * compare passage to transcript returns
     * @return external_value
     */
    public static function compare_passage_to_transcript_returns()
    {
        return new external_value(PARAM_RAW);
    }

    /**
     * evaluate transcript parameters
     * @return external_function_parameters
     */
    public static function evaluate_transcript_parameters()
    {
        return new external_function_parameters(
            [
                'transcript' => new external_value(PARAM_TEXT, 'The transcript of speaking or writing', VALUE_REQUIRED),
                'itemid' => new external_value(PARAM_INT, 'The item id in the minilesson', VALUE_REQUIRED),
                'cmid' => new external_value(PARAM_INT, 'The cmid', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * evaluate transcript
     * @param string $transcript
     * @param int $itemid
     * @param int $cmid
     * @return string
     */
    public static function evaluate_transcript($transcript, $itemid, $cmid)
    {
        global $DB;
        $ret = utils::evaluate_transcript($transcript, $itemid, $cmid);
        return json_encode($ret);
    }

    /**
     * evaluate transcript returns
     * @return external_value
     */
    public static function evaluate_transcript_returns()
    {
        return new external_value(PARAM_RAW);
    }

    /**
     * submit mform parameters
     * @return external_function_parameters
     */
    public static function submit_mform_parameters()
    {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                'jsonformdata' => new external_value(
                    PARAM_RAW,
                    'The data from the create group form, encoded as a json array'
                ),
                'formname' => new external_value(PARAM_TEXT, 'The formname'),
            ]
        );
    }

    /**
     * submit mform
     * @param string $contextid
     * @param string $jsonformdata
     * @param string $formname
     * @return string
     */
    public static function submit_mform($contextid, $jsonformdata, $formname)
    {
        global $CFG, $DB, $USER;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(
            self::submit_mform_parameters(),
            [
                'contextid' => $contextid,
                'jsonformdata' => $jsonformdata,
                'formname' => $formname,
            ]
        );

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

        // Init return object.
        $ret = new \stdClass();
        $ret->itemid = 0;
        $ret->error = true;
        $ret->message = "";

        [$ignored, $course] = get_context_info_array($context->id);
        $serialiseddata = json_decode($params['jsonformdata']);

        $data = [];
        parse_str($serialiseddata, $data);

        // Get filechooser and html editor options.
        $editoroptions = \mod_minilesson\local\itemtype\item::fetch_editor_options($course, $context);
        $filemanageroptions = \mod_minilesson\local\itemtype\item::fetch_filemanager_options($course, 3);

        // Get the objects we need.
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);

        // We need to pretend this was posted and these help.
        $method = 'post';
        $target = '';
        $attributes = null;
        $editable = true;

        $itemformclass  = utils::fetch_itemform_classname($formname);
        if (!$itemformclass) {
                print_error('No item type specifified');
                return 0;
        }
        $mform = new $itemformclass(
            null,
            [
                'editoroptions' => $editoroptions,
                'filemanageroptions' => $filemanageroptions,
                'moduleinstance' => $moduleinstance,
            ],
            $method,
            $target,
            $attributes,
            $editable,
            $data
        );

        $validateddata = $mform->get_data();
        if ($validateddata) {
            $edit = $validateddata->itemid ? true : false;
            // Currently data is an array, but it should be an object.
            $vdata = (object)$validateddata;
            $vdata->type = $formname;

            // Update or add.
            if ($edit) {
                $theitem = utils::fetch_item_from_itemrecord($vdata, $moduleinstance);
                $olditem = $DB->get_record(constants::M_QTABLE, ['id' => $vdata->itemid, constants::M_MODNAME => $cm->instance]);
            } else {
                $theitem = utils::fetch_item_from_itemrecord($vdata, $moduleinstance);
                $olditem = false;
            }

            // Remove bad accents and things that mess up transcription (kind of like clear but permanent).
            $theitem->deaccent();

            // Get passage hash.
            $theitem->update_create_langmodel($olditem);

            // Lets update the phonetics.
            $theitem->update_create_phonetic($olditem);

            $result = $theitem->update_insert_item();
            if ($result->error == true) {
                    $ret->message = $result->message;
            } else {
                $theitem = $result->item;
                $ret->itemid = $theitem->id;
                $ret->error = false;
            }
        }
            return json_encode($ret);
    }

    /**
     * submit mform returns
     * @return external_value
     */
    public static function submit_mform_returns()
    {
        return new external_value(PARAM_RAW);
    }

    /**
     * delete item parameters
     * @return external_function_parameters
     */
    public static function delete_item_parameters()
    {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                'itemid' => new external_value(PARAM_INT, 'The itemid to delete'),
                'formname' => new external_value(PARAM_TEXT, 'The formname'),
            ]
        );
    }

    /**
     * delete item
     * @param string $contextid
     * @param string $itemid
     * @param string $formname
     * @return string
     */
    public static function delete_item($contextid, $itemid, $formname)
    {
        global $CFG, $DB, $USER;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(
            self::delete_item_parameters(),
            ['contextid' => $contextid, 'itemid' => $itemid, 'formname' => $formname]
        );

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

        // DO DELETE
        // Get the objects we need.
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $success = \mod_minilesson\local\itemtype\item::delete_item($itemid, $context);

        $ret = new \stdClass();
        $ret->itemid = $itemid;
        $ret->error = false;
        return json_encode($ret);
    }

    /**
     * delete item returns
     * @return external_value
     */
    public static function delete_item_returns()
    {
        return new external_value(PARAM_RAW);
    }

    /**
     * move item parameters
     * @return external_function_parameters
     */
    public static function move_item_parameters()
    {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                'itemid' => new external_value(PARAM_INT, 'The itemid to move'),
                'direction' => new external_value(PARAM_TEXT, 'The move direction'),
            ]
        );
    }

    /**
     * move item
     * @param string $contextid
     * @param string $itemid
     * @param string $direction
     * @return string
     */
    public static function move_item($contextid, $itemid, $direction)
    {
        global $CFG, $DB, $USER;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(
            self::move_item_parameters(),
            ['contextid' => $contextid, 'itemid' => $itemid, 'direction' => $direction]
        );

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

        // DO move
        // get the objects we need.
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        \mod_minilesson\local\itemform\helper::move_item($moduleinstance, $itemid, $direction);

        $ret = new \stdClass();
        $ret->itemid = $itemid;
        $ret->error = false;
        return json_encode($ret);
    }

    /**
     * move item returns
     * @return external_value
     */
    public static function move_item_returns()
    {
        return new external_value(PARAM_RAW);
    }

    /**
     * duplicate item parameters
     * @return external_function_parameters
     */
    public static function duplicate_item_parameters()
    {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                'itemid' => new external_value(PARAM_INT, 'The itemid to move'),
            ]
        );
    }

    /**
     * duplicate item
     * @param string $contextid
     * @param string $itemid
     * @return string
     */
    public static function duplicate_item($contextid, $itemid)
    {
        global $CFG, $DB, $USER;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(
            self::duplicate_item_parameters(),
            ['contextid' => $contextid, 'itemid' => $itemid]
        );

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

        // DO move
        // Get the objects we need.
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        [$newitemid, $newitemname, $type, $typelabel] = \mod_minilesson\local\itemform\helper::duplicate_item(
            $moduleinstance,
            $context,
            $itemid
        );
        $iconurl = new moodle_url('/mod/minilesson/pix/' . $type . '.png', ['ver' => $CFG->themerev]);
        $ret = new \stdClass();
        $ret->olditemid = $itemid;
        $ret->newitemid = $newitemid;
        $ret->newitemname = $newitemname;
        $ret->icon = $iconurl->out();
        $ret->type = $type;
        $ret->typelabel = $typelabel;
        $ret->error = false;
        return json_encode($ret);
    }

    /**
     * duplicate item returns
     * @return external_value
     */
    public static function duplicate_item_returns()
    {
        return new external_value(PARAM_RAW);
    }


    /**
     * check grammar
     * @param string $text
     * @param string $language
     * @return string
     */
    public static function check_grammar($text, $language)
    {
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

        // If we have suggestions, mark those up and return them.
        $direction = "r2l";
        [$grammarerrors, $grammarmatches, $insertioncount] = utils::fetch_grammar_correction_diff($text, $suggestions, $direction);
        $markedupsuggestions = \mod_minilesson\aitranscriptutils::render_passage($suggestions, 'corrections');
        $ret = [];
        $ret['grammarerrors'] = $grammarerrors;
        $ret['grammarmatches'] = $grammarmatches;
        $ret['suggestions'] = $suggestions;
        $ret['markedupsuggestions'] = $markedupsuggestions;
        $ret['insertioncount'] = $insertioncount;

        return json_encode($ret);
    }

    /**
     * check grammar parameters
     * @return external_function_parameters
     */
    public static function check_grammar_parameters()
    {
        return new external_function_parameters([
            'text' => new external_value(PARAM_TEXT),
            'language' => new external_value(PARAM_TEXT),
        ]);
    }

    /**
     * check grammar returns
     * @return external_value
     */
    public static function check_grammar_returns()
    {
        return new external_value(PARAM_RAW);
    }


    public static function set_user_preference_parameters()
    {
        return new external_function_parameters([
            'name' => new external_value(PARAM_TEXT, 'The user preference name'),
            'value' => new external_value(PARAM_TEXT, 'The user preference value'),
        ]);
    }

    public static function set_user_preference($name, $value)
    {

        //set the user preference
        switch ($name) {
            case constants::NATIVELANG_PREF:
                if (empty($value)) {
                    unset_user_preference($name);
                } else {
                    set_user_preference($name, $value);
                }
                return true;
            default:
                return false;
        }
    }

    public static function set_user_preference_returns()
    {
        return new external_value(PARAM_BOOL);
    }

    /**
     * refresh token
     * @param string $type
     * @param string $region
     * @return string
     */
    public static function refresh_token($type, $region)
    {
        global $DB, $USER;
        $fulltoken = false;
        $params = self::validate_parameters(self::refresh_token_parameters(), [
            'type' => $type,
            'region' => $region]);
        extract($params);

        // Do the token refresh.
        switch ($type) {
            // Ms Speech gets its own token type - it uses speech assessment SDK so its not plain transcription.
            case 'msspeech':
                $fulltoken = utils::fetch_msspeech_token($region);
                break;

            case 'assemblyai':
            case 'azure':
            default:
                // The token type that will be fetched isdetermined by the region (and other config settings).
                // As long as its not msspeech .. it comes in here.
                $fulltoken = utils::fetch_streaming_token($region);
        }
        return json_encode($fulltoken);
    }

    /**
     * refresh token parameters
     * @return external_function_parameters
     */
    public static function refresh_token_parameters()
    {
        return new external_function_parameters([
            'type' => new external_value(PARAM_TEXT),
            'region' => new external_value(PARAM_TEXT),
        ]);
    }

    /**
     * refresh token returns
     * @return external_value
     */
    public static function refresh_token_returns()
    {
        return new external_value(PARAM_RAW);
    }

    /**
     * lesson bank parameters
     * @return external_function_parameters
     */
    public static function lessonbank_parameters()
    {
        return new external_function_parameters([
            'function' => new external_value(PARAM_TEXT),
            'args' => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * lesson bank
     * @param string $function
     * @param string $args
     * @return stdClass
     */
    public static function lessonbank($function, $args = '')
    {
        $params = self::validate_parameters(self::lessonbank_parameters(), [
            'function' => $function,
            'args' => $args,
        ]);

        parse_str($args, $json);

        $lessonbankurl = get_config('mod_minilesson', 'lessonbankurl');
        $url = "{$lessonbankurl}/lib/ajax/service-nologin.php";
        $info = [
            [
                'methodname' => $function,
                'args' => $json,
            ],
        ];

        $curl = new curl();
        $curl->setHeader(['Content-Type: application/json']);
        $response = $curl->post($url, json_encode($info));
        $result = json_decode($response, true);

        $ret = new \stdClass();
        if ($result === null || json_last_error()) {
            $ret->error = true;
        } else {
            $ret1 = $result[0];
            if (empty($ret1['error'])) {
                $ret->data = json_encode($ret1['data']);
            } elseif (!empty($ret1['exception'])) {
                $ret->error = $ret1['exception']['message'];
            } else {
                $ret->error = true;
            }
        }
        return $ret;
    }

    /**
     * lesson bank returns
     * @return external_single_structure
     */
    public static function lessonbank_returns()
    {
        return new external_single_structure([
            'error' => new external_value(PARAM_BOOL, 'has error', VALUE_DEFAULT, false),
            'data' => new external_value(PARAM_RAW, 'json encoded data', VALUE_DEFAULT),
        ]);
    }
}
