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
 * Utils for minilesson plugin
 *
 * @package    mod_minilesson
 * @copyright  2020 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 namespace mod_minilesson;
defined('MOODLE_INTERNAL') || die();

use mod_minilesson\constants;


/**
 * Functions used generally across this mod
 *
 * @package    mod_minilesson
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {

    // const CLOUDPOODLL = 'http://localhost/moodle';
    // const CLOUDPOODLL = 'https://vbox.poodll.com/cphost';
    const CLOUDPOODLL = 'https://cloud.poodll.com';

    // we need to consider legacy client side URLs and cloud hosted ones
    public static function make_audio_url($filename, $contextid, $component, $filearea, $itemid) {
        // we need to consider legacy client side URLs and cloud hosted ones
        if(strpos($filename, 'http') === 0){
            $ret = $filename;
        }else {
            $ret = \moodle_url::make_pluginfile_url($contextid, $component,
                $filearea,
                $itemid, '/',
                $filename);
        }
        return $ret;
    }


    /*
    * Do we need to build a language model for this passage?
    *
    */
    public static function needs_lang_model($moduleinstance, $passage) {
        switch($moduleinstance->region){

            case 'capetown':
            case 'bahrain':
            case 'tokyo':
            case 'useast1':
            case 'dublin':
            case 'sydney':
            default:
                $shortlang = self::fetch_short_lang($moduleinstance->ttslanguage);
                return ($shortlang == 'en' ||
                        $shortlang == 'de' ||
                        $shortlang == 'fr' ||
                        $shortlang == 'ru' ||
                        $shortlang == 'eu' ||
                        $shortlang == 'pl' ||
                        $shortlang == 'fi' ||
                        $shortlang == 'it' ||
                        $shortlang == 'pt' ||
                        $shortlang == 'uk' ||
                        $shortlang == 'hu' ||
                        $shortlang == 'ro' ||
                        $shortlang == 'es') && trim($passage) !== "";
        }
    }

    /*
     * Hash the passage and compare
     *
     */
    public static function fetch_passagehash($ttslanguage, $passage) {

        $cleanpassage = self::fetch_clean_passage($passage);

        // number or odd char converter
        $shortlang = self::fetch_short_lang($ttslanguage);
        if($shortlang == 'en' || $shortlang == 'de' ){
            // find numbers in the passage, and then replace those with words in the target text
            switch ($shortlang){
                case 'en':
                    $cleanpassage = alphabetconverter::numbers_to_words_convert($cleanpassage, $cleanpassage, $shortlang);
                    break;
                case 'de':
                    $cleanpassage = alphabetconverter::eszett_to_ss_convert($cleanpassage, $cleanpassage);
                    break;

            }
        }

        if(!empty($cleanpassage)) {
            return sha1($cleanpassage);
        }else{
            return false;
        }
    }

    public static function fetch_short_lang($longlang) {
        if(\core_text::strlen($longlang) <= 2){return $longlang;
        }
        if($longlang == "fil-PH"){return "fil";
        }
        $shortlang = substr($longlang, 0, 2);
        return $shortlang;
    }

    /*
     * Hash the passage and compare
     *
     */
    public static function fetch_clean_passage($passage) {
        $sentences = explode(PHP_EOL, $passage);
        $usesentences = [];
        // look out for display text sep. by pipe chars in string
        foreach($sentences as $sentence){
            $sentencebits = explode('|', $sentence);
            if(count($sentencebits) > 1){
                $usesentences[] = trim($sentencebits[1]);
            }else{
                $usesentences[] = $sentence;
            }
        }
        $usepassage = implode(PHP_EOL, $usesentences);

        $cleantext = diff::cleanText($usepassage);
        if(!empty($cleantext)) {
            return $cleantext;
        }else{
            return false;
        }
    }


    /*
     * Build a language model for this text
     *
     */
    public static function fetch_lang_model($passage, $language, $region) {
        $usepassage = self::fetch_clean_passage($passage);
        if($usepassage === false ){return false;
        }

        // get our 2 letter lang code
        $shortlang = self::fetch_short_lang($language);

        // find digits in original passage, and convert number words to digits in the target passage
        $usepassage = alphabetconverter::numbers_to_words_convert($usepassage, $usepassage, $shortlang);

        // other conversions
        switch ($shortlang){

            case 'de':
                // find eszetts in original passage, and convert ss words to eszetts in the target passage
                $params["passage"] = alphabetconverter::eszett_to_ss_convert($usepassage, $usepassage);
                break;

        }

        $conf = get_config(constants::M_COMPONENT);
        if (!empty($conf->apiuser) && !empty($conf->apisecret)) {
            $token = self::fetch_token($conf->apiuser, $conf->apisecret);
            // $token = self::fetch_token('russell', 'Password-123',true);

            if(empty($token)){
                return false;
            }
            $url = self::CLOUDPOODLL . "/webservice/rest/server.php";
            $params["wstoken"] = $token;
            $params["wsfunction"] = 'local_cpapi_generate_lang_model';
            $params["moodlewsrestformat"] = 'json';
            $params["passage"] = $usepassage;
            $params["language"] = $language;
            $params["region"] = $region;

            $resp = self::curl_fetch($url, $params);
            $respobj = json_decode($resp);
            $ret = new \stdClass();
            if(isset($respobj->returnCode)){
                $ret->success = $respobj->returnCode == '0' ? true : false;
                $ret->payload = $respobj->returnMessage;
            }else{
                $ret->success = false;
                $ret->payload = "unknown problem occurred";
            }
            return $ret;
        }else{
            return false;
        }
    }

    // reset the item order for a minilesson
    public static function reset_item_order($minilessonid) {
        global $DB;

        $allitems = $DB->get_records(constants::M_QTABLE, ['minilesson' => $minilessonid], 'itemorder ASC');
        if($allitems &&count($allitems) > 0 ){
            $i = 0;
            foreach($allitems as $theitem){
                $i++;
                $theitem->itemorder = $i + 1;
                $DB->update_record(constants::M_QTABLE, $theitem);
            }
        }
    }

    public static function xxx_update_final_grade($cmid, $stepresults, $attemptid) {

        global $USER, $DB;

        $result = false;
        $message = '';
        $returndata = false;

        $cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid, 0, false, MUST_EXIST);
        $moduleinstance  = $DB->get_record(constants::M_MODNAME, ['id' => $cm->instance], '*', MUST_EXIST);
        $attempt = $DB->get_record(constants::M_ATTEMPTSTABLE, ['id' => $attemptid, 'userid' => $USER->id]);

        $correctitems = 0;
        $totalitems = 0;
        foreach($stepresults as $result){
            if($result->hasgrade) {
                $correctitems += $result->correctitems;
                $totalitems += $result->totalitems;
            }
        }
        $totalpercent = round($correctitems / $totalitems, 2) * 100;

        if($attempt) {

            // grade quiz results
            // $useresults = json_decode($stepresults);
            // $answers = $useresults->answers;
            // $comp_test =  new comprehensiontest($cm);
            // $score= $comp_test->grade_test($answers);
            $attempt->sessionscore = $totalpercent;
            $attempt->sessiondata = json_encode($stepresults);

            $result = $DB->update_record(constants::M_ATTEMPTSTABLE, $attempt);
            if($result) {
                $returndata = '';
                minilesson_update_grades($moduleinstance, $USER->id, false);
            }else{
                $message = 'unable to update attempt record';
            }
        }else{
            $message = 'no attempt of that id for that user';
        }
        // return_to_page($result,$message,$returndata);
        return [$result, $message, $returndata];
    }

    public static function update_step_grade($cmid, $stepdata) {

        global $CFG, $USER, $DB;

        $message = '';
        $returndata = false;

        $cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid, 0, false, MUST_EXIST);
        $modulecontext = \context_module::instance($cm->id);
        $moduleinstance  = $DB->get_record(constants::M_MODNAME, ['id' => $cm->instance], '*', MUST_EXIST);
        $attempts = $DB->get_records(constants::M_ATTEMPTSTABLE, ['moduleid' => $moduleinstance->id, 'userid' => $USER->id], 'id DESC');

        // Get or create attempt.
        if (!$attempts) {
            $latestattempt = self::create_new_attempt($moduleinstance->course, $moduleinstance->id);
        } else {
            $latestattempt = reset($attempts);
        }

        // Get or create sessiondata.
        if (empty($latestattempt->sessiondata)) {
            $sessiondata = new \stdClass();
            $sessiondata->steps = [];
        } else {
            $sessiondata = json_decode($latestattempt->sessiondata);
        }

        // if sessiondata is not an array, reconstruct it as an array
        if (!is_array($sessiondata->steps)) {
            $sessiondata->steps = self::remake_steps_as_array($sessiondata->steps);
        }
        // add our latest step to session
        $sessiondata->steps[$stepdata->index] = $stepdata;

        // grade quiz results
        $comptest = new comprehensiontest($cm);
        $totalitems = $comptest->fetch_item_count();

        // raise step submitted event
        $latestattempt->sessiondata = json_encode($sessiondata);
        \mod_minilesson\event\step_submitted::create_from_attempt($latestattempt, $modulecontext, $stepdata->index)->trigger();

        // close out the attempt and update the grade
        // there should never be more steps than items
        // [hack] but there seem to be times when there are fewer( when an update_step_grade failed or didnt arrive),
        // so we also allow the final item. Though it's not ideal because we will have missed one or more
        if($totalitems <= count($sessiondata->steps) || $stepdata->index == $totalitems - 1) {
            $newgrade = true;
            $latestattempt->sessionscore = self::calculate_session_score($sessiondata->steps);
            $latestattempt->status = constants::M_STATE_COMPLETE;
            \mod_minilesson\event\attempt_submitted::create_from_attempt($latestattempt, $modulecontext)->trigger();
        }else{
            $newgrade = false;
        }

        // update the record
        $result = $DB->update_record(constants::M_ATTEMPTSTABLE, $latestattempt);
        if($result) {
            $returndata = '';
            if($newgrade) {
                require_once($CFG->dirroot . constants::M_PATH . '/lib.php');
                minilesson_update_grades($moduleinstance, $USER->id, false);
                // tell JS about the grade situation

            }
        }else{
            $message = 'unable to update attempt record';
        }

        // return_to_page($result,$message,$returndata);
        return [$result, $message, $returndata];
    }

    // JSON stringify functions will make objects(not arrays) if keys are not sequential
    // sometimes we seem to miss a step. Remedying that with this function prevents an all out disaster.
    // But we should not miss steps
    public static function remake_steps_as_array($stepsobject) {
        if(is_array($stepsobject)) {
            return $stepsobject;
        }else{
            $steps = [];
            foreach ($stepsobject as $key => $value)
            {
                if(is_numeric($key)){
                    $key = intval($key);
                    $steps[$key] = $value;
                }

            }
            return $steps;
        }
    }

    public static function calculate_session_score($steps) {
        $results = array_filter($steps, function($step){return $step->hasgrade;
        });
        $correctitems = 0;
        $totalitems = 0;
        foreach($results as $result){
            $correctitems += $result->correctitems;
            $totalitems += $result->totalitems;
        }
        $totalpercent = round(($correctitems / $totalitems) * 100, 0);
        return $totalpercent;
    }


    public static function create_new_attempt($courseid, $moduleid) {
        global $DB, $USER;

        $newattempt = new \stdClass();
        $newattempt->courseid = $courseid;
        $newattempt->moduleid = $moduleid;
        $newattempt->status = constants::M_STATE_INCOMPLETE;
        $newattempt->userid = $USER->id;
        $newattempt->timecreated = time();
        $newattempt->timemodified = time();

        $newattempt->id = $DB->insert_record(constants::M_ATTEMPTSTABLE, $newattempt);
        return $newattempt;

    }

    // De accent and other processing so our auto transcript will match the passage
    public static function remove_accents_and_poormatchchars($text, $language) {
        switch($language){
            case constants::M_LANG_UKUA:
                $ret = str_replace(
                    ["е́", "о́", "у́", "а́", "и́", "я́", "ю́", "Е́", "О́", "У́", "А́", "И́", "Я́", "Ю́", "“", "”", "'", "́"],
                    ["е", "о", "у", "а", "и", "я", "ю", "Е", "О", "У", "А", "И", "Я", "Ю", "\"", "\"", "’", ""],
                    $text
                );
                break;
            default:
                $ret = $text;
        }
        return $ret;
    }


    // are we willing and able to transcribe submissions?
    public static function can_transcribe($instance) {

        // we default to true
        // but it only takes one no ....
        $ret = true;

        // The regions that can transcribe
        switch($instance->region){
            default:
                $ret = true;
        }

        return $ret;
    }

    // see if this is truly json or some error
    public static function is_json($string) {
        if(!$string){return false;
        }
        if(empty($string)){return false;
        }
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    // we use curl to fetch transcripts from AWS and Tokens from cloudpoodll
    // this is our helper
    public static function curl_fetch($url, $postdata=false, $method='get') {
        global $CFG;

        require_once($CFG->libdir.'/filelib.php');
        $curl = new \curl();

        if($method == 'get') {
            $result = $curl->get($url, $postdata);
        }else{
            $result = $curl->post($url, $postdata);
        }
        return $result;
    }

    // This is called from the settings page and we do not want to make calls out to cloud.poodll.com on settings
    // page load, for performance and stability issues. So if the cache is empty and/or no token, we just show a
    // "refresh token" links
    public static function fetch_token_for_display($apiuser, $apisecret) {
        global $CFG;

        // First check that we have an API id and secret
        // refresh token
        $refresh = \html_writer::link($CFG->wwwroot . '/mod/minilesson/refreshtoken.php',
                get_string('refreshtoken', constants::M_COMPONENT)) . '<br>';

        $message = '';
        $apiuser = trim($apiuser);
        $apisecret = trim($apisecret);
        if(empty($apiuser)){
            $message .= get_string('noapiuser', constants::M_COMPONENT) . '<br>';
        }
        if(empty($apisecret)){
            $message .= get_string('noapisecret', constants::M_COMPONENT);
        }

        if(!empty($message)){
            return $refresh . $message;
        }

        // Fetch from cache and process the results and display
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');

        // if we have no token object the creds were wrong ... or something
        if(!($tokenobject)){
            $message = get_string('notokenincache', constants::M_COMPONENT);
            // if we have an object but its no good, creds werer wrong ..or something
        }else if(!property_exists($tokenobject, 'token') || empty($tokenobject->token)){
            $message = get_string('credentialsinvalid', constants::M_COMPONENT);
            // if we do not have subs, then we are on a very old token or something is wrong, just get out of here.
        }else if(!property_exists($tokenobject, 'subs')){
            $message = 'No subscriptions found at all';
        }
        if(!empty($message)){
            return $refresh . $message;
        }

        // we have enough info to display a report. Lets go.
        foreach ($tokenobject->subs as $sub){
            $sub->expiredate = date('d/m/Y', $sub->expiredate);
            $message .= get_string('displaysubs', constants::M_COMPONENT, $sub) . '<br>';
        }
        // Is app authorised
        if(in_array(constants::M_COMPONENT, $tokenobject->apps)){
            $message .= get_string('appauthorised', constants::M_COMPONENT) . '<br>';
        }else{
            $message .= get_string('appnotauthorised', constants::M_COMPONENT) . '<br>';
        }

        return $refresh . $message;

    }

    // We need a Poodll token to make all this recording and transcripts happen
    public static function fetch_token($apiuser, $apisecret, $force=false) {

        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');
        $tokenuser = $cache->get('recentpoodlluser');
        $apiuser = trim($apiuser);
        $apisecret = trim($apisecret);
        $now = time();

        // if we got a token and its less than expiry time
        // use the cached one
        if($tokenobject && $tokenuser && $tokenuser == $apiuser && !$force){
            if($tokenobject->validuntil == 0 || $tokenobject->validuntil > $now){
                // $hoursleft= ($tokenobject->validuntil-$now) / (60*60);
                return $tokenobject->token;
            }
        }

        // Send the request & save response to $resp
        $tokenurl = self::CLOUDPOODLL . "/local/cpapi/poodlltoken.php";
        $postdata = [
            'username' => $apiuser,
            'password' => $apisecret,
            'service' => 'cloud_poodll',
        ];
        $tokenresponse = self::curl_fetch($tokenurl, $postdata);
        if ($tokenresponse) {
            $respobject = json_decode($tokenresponse);
            if($respobject && property_exists($respobject, 'token')) {
                $token = $respobject->token;
                // store the expiry timestamp and adjust it for diffs between our server times
                if($respobject->validuntil) {
                    $validuntil = $respobject->validuntil - ($respobject->poodlltime - $now);
                    // we refresh one hour out, to prevent any overlap
                    $validuntil = $validuntil - (1 * HOURSECS);
                }else{
                    $validuntil = 0;
                }

                $tillrefreshhoursleft = ($validuntil - $now) / (60 * 60);

                // cache the token
                $tokenobject = new \stdClass();
                $tokenobject->token = $token;
                $tokenobject->validuntil = $validuntil;
                $tokenobject->subs = false;
                $tokenobject->apps = false;
                $tokenobject->sites = false;
                if(property_exists($respobject, 'subs')){
                    $tokenobject->subs = $respobject->subs;
                }
                if(property_exists($respobject, 'apps')){
                    $tokenobject->apps = $respobject->apps;
                }
                if(property_exists($respobject, 'sites')){
                    $tokenobject->sites = $respobject->sites;
                }

                $cache->set('recentpoodlltoken', $tokenobject);
                $cache->set('recentpoodlluser', $apiuser);

            }else{
                $token = '';
                if($respobject && property_exists($respobject, 'error')) {
                    // ERROR = $resp_object->error
                }
            }
        }else{
            $token = '';
        }
        return $token;
    }

    // check token and tokenobject(from cache)
    // return error message or blank if its all ok
    public static function fetch_token_error($token) {
        global $CFG;

        // check token authenticated
        if(empty($token)) {
            $message = get_string('novalidcredentials', constants::M_COMPONENT,
                    $CFG->wwwroot . constants::M_PLUGINSETTINGS);
            return $message;
        }

        // Fetch from cache and process the results and display.
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');

        // we should not get here if there is no token, but lets gracefully die, [v unlikely]
        if (!($tokenobject)) {
            $message = get_string('notokenincache', constants::M_COMPONENT);
            return $message;
        }

        // We have an object but its no good, creds were wrong ..or something. [v unlikely]
        if (!property_exists($tokenobject, 'token') || empty($tokenobject->token)) {
            $message = get_string('credentialsinvalid', constants::M_COMPONENT);
            return $message;
        }
        // if we do not have subs.
        if (!property_exists($tokenobject, 'subs')) {
            $message = get_string('nosubscriptions', constants::M_COMPONENT);
            return $message;
        }
        // Is app authorised?
        if (!property_exists($tokenobject, 'apps') || !in_array(constants::M_COMPONENT, $tokenobject->apps)) {
            $message = get_string('appnotauthorised', constants::M_COMPONENT);
            return $message;
        }

        // just return empty if there is no error.
        return '';
    }

    // stage remote processing job ..just logging really
    public static function stage_remote_process_job($language, $cmid) {

        global $CFG, $USER;

        $token = false;
        $conf = get_config(constants::M_COMPONENT);
        if (!empty($conf->apiuser) && !empty($conf->apisecret)) {
            $token = self::fetch_token($conf->apiuser, $conf->apisecret);
        }
        if(!$token || empty($token)){
            return false;
        }

        $host = parse_url($CFG->wwwroot, PHP_URL_HOST);
        if (!$host) {
            $host = "unknown";
        }
        // owner
        $owner = hash('md5', $USER->username);
        $ownercomphash = hash('md5', $USER->username . constants::M_COMPONENT . $cmid .  date("Y-m-d"));

        // The REST API we are calling
        $functionname = 'local_cpapi_stage_remoteprocess_job';

        // log.debug(params);
        $params = [];
        $params['wstoken'] = $token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['appid'] = constants::M_COMPONENT;
        $params['region'] = $conf->awsregion;
        $params['host'] = $host;
        $params['s3outfilename'] = $ownercomphash; // we just want a unique value per session here
        $params['owner'] = $owner;
        $params['transcode'] = '0';
        $params['transcoder'] = 'default';
        $params['transcribe'] = '0';
        $params['subtitle'] = '0';
        $params['language'] = $language;
        $params['vocab'] = 'none';
        $params['s3path'] = '/';
        $params['mediatype'] = 'other';
        $params['notificationurl'] = 'none';
        $params['sourcemimetype'] = 'unknown';

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        // returnCode > 0  indicates an error
        if ($payloadobject->returnCode > 0) {
            return false;
            // if all good, then lets just return true
        } else if ($payloadobject->returnCode === 0) {
            return true;
        } else {
            return false;
        }
    }

    public static function evaluate_transcript($transcript, $itemid, $cmid) {
        global $CFG, $USER, $DB, $OUTPUT;

        $token = false;
        $conf = get_config(constants::M_COMPONENT);
        if (!empty($conf->apiuser) && !empty($conf->apisecret)) {
            $token = self::fetch_token($conf->apiuser, $conf->apisecret);
        }
        if(!$token || empty($token)){
            return false;
        }
        $cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_MODNAME, ['id' => $cm->instance], '*', MUST_EXIST);
        $item = $DB->get_record(constants::M_QTABLE, ['id' => $itemid, 'minilesson' => $moduleinstance->id], '*', MUST_EXIST);

        //Feedback language for AI instructions
        //its awful but we hijack the wordcards student native language setting
        $feedbacklanguage = $item->{constants::AIGRADE_FEEDBACK_LANGUAGE};
        if ($conf->setnativelanguage) {
            $userprefdeflanguage = get_user_preferences('wordcards_deflang');
            if (!empty($userprefdeflanguage)) {
                //the WC language is 2 char but Poodll AI expects a locale code
                $wclanguage = self::lang_to_locale($userprefdeflanguage);
                //if we did get a locale code lets use that.
                if ($wclanguage !== $userprefdeflanguage && $wclanguage !== $feedbacklanguage) {
                    $feedbacklanguage = $wclanguage;
                }
            }
        }

        // AI Grade
        $maxmarks = $item->{constants::TOTALMARKS};
        $instructions = new \stdClass();
        $instructions->feedbackscheme = $item->{constants::AIGRADE_FEEDBACK};
        $instructions->feedbacklanguage = $feedbacklanguage;
        $instructions->markscheme = $item->{constants::AIGRADE_INSTRUCTIONS};
        $instructions->maxmarks = $maxmarks;
        $instructions->questiontext = strip_tags($item->itemtext);
        $instructions->modeltext = $item->{constants::AIGRADE_MODELANSWER};
        $aigraderesults = self::fetch_ai_grade($token, $moduleinstance->region,
         $moduleinstance->ttslanguage, $transcript, $instructions);

         // Mark up AI Grade corrections
        if ($aigraderesults && isset($aigraderesults->correctedtext)) {
            // if we have corrections mark those up and return them
            $direction = "r2l";// "l2r";
            list($grammarerrors, $grammarmatches, $insertioncount) = self::fetch_grammar_correction_diff($transcript, $aigraderesults->correctedtext, $direction);
            $aigraderesults->markedupcorrections = aitranscriptutils::render_passage($aigraderesults->correctedtext, 'corrections');
            $aigraderesults->markeduppassage = aitranscriptutils::render_passage($transcript, 'passage');
            $aigraderesults->grammarerrors = $grammarerrors;
            $aigraderesults->grammarmatches  = $grammarmatches;
            $aigraderesults->insertioncount  = $insertioncount;
        }

         // STATS
        $userlanguage = false;
        $targetembedding = false;
        $targettopic = false;
        $targetwords = [];
        if($item->{constants::RELEVANCE} == constants::RELEVANCETYPE_QUESTION){
            $targettopic = strip_tags($item->itemtext);
        }else{
            $targetembedding = $item->{constants::AIGRADE_MODELANSWER};
        }
        $textanalyser = new textanalyser($token, $transcript, $moduleinstance->region,
        $moduleinstance->ttslanguage, $targetembedding, $userlanguage, $targettopic);
        $aigraderesults->stats = $textanalyser->process_some_stats($targetwords);

        return $aigraderesults;

    }

    //This function takes the 2-character language code ($lang) as input and returns the corresponding locale code.
    public static function lang_to_locale($lang)
    {
        switch ($lang) {
            case 'ar':
                return 'ar-AE'; // Assuming Arabic (Modern Standard) is the default
            case 'en':
                return 'en-US'; // Assuming US English is the default
            case 'es':
                return 'es-ES'; // Assuming Spanish (Spain) is the default
            case 'fr':
                return 'fr-FR'; // Assuming French (France) is the default
            case 'id':
                return 'id-ID'; // Assuming Indonesian (Indonesia) is the default
            case 'ja':
                return 'ja-JP'; // Assuming Japanese (Japan) is the default
            case 'ko':
                return 'ko-KR'; // Assuming Korean (South Korea) is the default
            case 'pt':
                return 'pt-BR'; // Assuming Brazilian Portuguese is the default
            case 'ru':
            case 'rus':
                return 'ru-RU'; // Assuming Russian (Russia) is the default
            case 'zh':
                return 'zh-CN'; // Assuming Chinese (Simplified, China) is the default
            case 'zh_tw':
                return 'zh-TW'; // Assuming Chinese (Traditional, Taiwan) is the default
            case 'af':
                return 'af-ZA'; // Assuming Afrikaans (South Africa) is the default
            case 'bn':
                return 'bn-BD'; // Assuming Bengali (Bangladesh) is the default
            case 'bs':
                return 'bs-Latn-BA'; // Assuming Bosnian (Latin, Bosnia and Herzegovina) is the default
            case 'bg':
                return 'bg-BG'; // Assuming Bulgarian (Bulgaria) is the default
            case 'ca':
                return 'ca-ES'; // Assuming Catalan (Spain) is the default
            case 'hr':
                return 'hr-HR'; // Assuming Croatian (Croatia) is the default
            case 'cs':
                return 'cs-CZ'; // Assuming Czech (Czech Republic) is the default
            case 'da':
                return 'da-DK'; // Assuming Danish (Denmark) is the default
            case 'nl':
                return 'nl-NL'; // Assuming Dutch (Netherlands) is the default
            case 'fi':
                return 'fi-FI'; // Assuming Finnish (Finland) is the default
            case 'de':
                return 'de-DE'; // Assuming German (Germany) is the default
            case 'el':
                return 'el-GR'; // Assuming Greek (Greece) is the default
            case 'ht':
                return 'ht-HT'; // Assuming Haitian Creole (Haiti) is the default
            case 'he':
                return 'he-IL'; // Assuming Hebrew (Israel) is the default
            case 'hi':
                return 'hi-IN'; // Assuming Hindi (India) is the default
            case 'hu':
                return 'hu-HU'; // Assuming Hungarian (Hungary) is the default
            case 'is':
                return 'is-IS'; // Assuming Icelandic (Iceland) is the default
            case 'it':
                return 'it-IT'; // Assuming Italian (Italy) is the default
            case 'lv':
                return 'lv-LV'; // Assuming Latvian (Latvia) is the default
            case 'lt':
                return 'lt-LT'; // Assuming Lithuanian (Lithuania) is the default
            case 'ms':
                return 'ms-MY'; // Assuming Malay (Malaysia) is the default
            case 'mt':
                return 'mt-MT'; // Assuming Maltese (Malta) is the default
            case 'nb':
                return 'nb-NO'; // Assuming Norwegian Bokmål (Norway) is the default
            case 'fa':
                return 'fa-IR'; // Assuming Persian (Iran) is the default
            case 'pl':
                return 'pl-PL'; // Assuming Polish (Poland) is the default
            case 'ro':
                return 'ro-RO'; // Assuming Romanian (Romania) is the default
            case 'sr':
                return 'sr-Latn-RS'; // Assuming Serbian (Latin, Serbia) is the default
            case 'sk':
                return 'sk-SK'; // Assuming Slovak (Slovakia) is the default
            case 'sl':
                return 'sl-SI'; // Assuming Slovenian (Slovenia) is the default
            case 'sw':
                return 'sw-KE'; // Assuming Swahili (Kenya) is the default
            case 'sv':
                return 'sv-SE'; // Assuming Swedish (Sweden) is the default
            case 'ta':
                return 'ta-IN'; // Assuming Tamil (India) is the default
            case 'tr':
                return 'tr-TR'; // Assuming Turkish (Turkey) is the default
            case 'uk':
                return 'uk-UA'; // Assuming Ukrainian (Ukraine) is the default
            case 'ur':
                return 'ur-PK'; // Assuming Urdu (Pakistan) is the default
            case 'cy':
                return 'cy-GB'; // Assuming Welsh (United Kingdom) is the default
            case 'vi':
                return 'vi-VN'; // Assuming Vietnamese (Vietnam) is the default
            default:
                return $lang; // If no match, return the original lang code
        }
    }


    public static function fetch_grammar_correction_diff($selftranscript, $correction, $direction='l2r') {

        // turn the passage and transcript into an array of words
        $alternatives = diff::fetchAlternativesArray('');
        $wildcards = diff::fetchWildcardsArray($alternatives);

        // the direction of diff depends on which text we want to mark up. Because we only highlight
        // this is because if we show the pre-text (eg student typed text) we can not highlight corrections .. they are not there
        // if we show post-text (eg corrections) we can not highlight mistakes .. they are not there
        // the diffs tell us where the diffs are with relation to text A
        if($direction == 'l2r') {
            $passagebits = diff::fetchWordArray($selftranscript);
            $transcriptbits = diff::fetchWordArray($correction);
        }else {
            $passagebits = diff::fetchWordArray($correction);
            $transcriptbits = diff::fetchWordArray($selftranscript);
        }

        // fetch sequences of transcript/passage matched words
        // then prepare an array of "differences"
        $passagecount = count($passagebits);
        $transcriptcount = count($transcriptbits);
        // rough estimate of insertions
        $insertioncount = $transcriptcount - $passagecount;
        if($insertioncount < 0){$insertioncount = 0;
        }

        $language = constants::M_LANG_ENUS;
        $sequences = diff::fetchSequences($passagebits, $transcriptbits, $alternatives, $language);

        // fetch diffs
        $diffs = diff::fetchDiffs($sequences, $passagecount, $transcriptcount);
        $diffs = diff::applyWildcards($diffs, $passagebits, $wildcards);

        // from the array of differences build error data, match data, markers, scores and metrics
        $errors = new \stdClass();
        $matches = new \stdClass();
        $currentword = 0;
        $lastunmodified = 0;
        // loop through diffs
        foreach($diffs as $diff){
            $currentword++;
            switch($diff[0]){
                case Diff::UNMATCHED:
                    // we collect error info so we can count and display them on passage
                    $error = new \stdClass();
                    $error->word = $passagebits[$currentword - 1];
                    $error->wordnumber = $currentword;
                    $errors->{$currentword} = $error;
                    break;

                case Diff::MATCHED:
                    // we collect match info so we can play audio from selected word
                    $match = new \stdClass();
                    $match->word = $passagebits[$currentword - 1];
                    $match->pposition = $currentword;
                    $match->tposition = $diff[1];
                    $match->audiostart = 0;// not meaningful when processing corrections
                    $match->audioend = 0;// not meaningful when processing corrections
                    $match->altmatch = $diff[2];// not meaningful when processing corrections
                    $matches->{$currentword} = $match;
                    $lastunmodified = $currentword;
                    break;

                default:
                    // do nothing
                    // should never get here

            }
        }
        $sessionendword = $lastunmodified;

        // discard errors that happen after session end word.
        $errorcount = 0;
        $finalerrors = new \stdClass();
        foreach($errors as $key => $error) {
            if ($key < $sessionendword) {
                $finalerrors->{$key} = $error;
                $errorcount++;
            }
        }
        // finalise and serialise session errors
        $sessionerrors = json_encode($finalerrors);
        $sessionmatches = json_encode($matches);

        return [$sessionerrors, $sessionmatches, $insertioncount];
    }

      // fetch the AI Grade
    public static function fetch_ai_grade($token, $region, $ttslanguage, $studentresponse, $instructions) {
        global $USER;
        $instructionsjson = json_encode($instructions);
        // The REST API we are calling
        $functionname = 'local_cpapi_call_ai';

        $params = [];
        $params['wstoken'] = $token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['action'] = 'autograde_text';
        $params['appid'] = 'mod_solo';
        $params['prompt'] = $instructionsjson;
        $params['language'] = $ttslanguage;
        $params['subject'] = $studentresponse;
        $params['region'] = $region;
        $params['owner'] = hash('md5', $USER->username);

        // log.debug(params);

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        // returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            // if all good, then lets return
        } else if ($payloadobject->returnCode === 0) {
            $autograderesponse = $payloadobject->returnMessage;
            // clean up the correction a little
            if(\core_text::strlen($autograderesponse) > 0 && self::is_json($autograderesponse)){
                $autogradeobj = json_decode($autograderesponse);
                if(isset($autogradeobj->feedback) && $autogradeobj->feedback == null){
                    unset($autogradeobj->feedback);
                }
                if(isset($autogradeobj->marks) && $autogradeobj->marks == null){
                    unset($autogradeobj->marks);
                }
                return $autogradeobj;
            }else{
                return false;
            }
        } else {
            return false;
        }
    }


    /*
     * Turn a passage with text "lines" into html "brs"
     *
     * @param String The passage of text to convert
     * @param String An optional pad on each replacement (needed for processing when marking up words as spans in passage)
     * @return String The converted passage of text
     */
    public static function lines_to_brs($passage, $seperator='') {
        // see https://stackoverflow.com/questions/5946114/how-to-replace-newline-or-r-n-with-br
        return str_replace("\r\n", $seperator . '<br>' . $seperator, $passage);
        // this is better but we can not pad the replacement and we need that
        // return nl2br($passage);
    }


    // take a json string of session errors/self-corrections, and count how many there are.
    public static function count_objects($items) {
        $objects = json_decode($items);
        if($objects){
            $thecount = count(get_object_vars($objects));
        }else{
            $thecount = 0;
        }
        return $thecount;
    }

     /**
      * Returns the link for the related activity
      * @return stdClass
      */
    public static function fetch_next_activity($activitylink) {
        global $DB;
        $ret = new \stdClass();
        $ret->url = false;
        $ret->label = false;
        if(!$activitylink){
            return $ret;
        }

        $module = $DB->get_record('course_modules', ['id' => $activitylink]);
        if ($module) {
            $modname = $DB->get_field('modules', 'name', ['id' => $module->module]);
            if ($modname) {
                $instancename = $DB->get_field($modname, 'name', ['id' => $module->instance]);
                if ($instancename) {
                    $ret->url = new \moodle_url('/mod/'.$modname.'/view.php', ['id' => $activitylink]);
                    $ret->label = get_string('activitylinkname', constants::M_COMPONENT, $instancename);
                }
            }
        }
        return $ret;
    }

    public static function get_region_options() {
        return [
        "useast1" => get_string("useast1", constants::M_COMPONENT),
          "tokyo" => get_string("tokyo", constants::M_COMPONENT),
          "sydney" => get_string("sydney", constants::M_COMPONENT),
          "dublin" => get_string("dublin", constants::M_COMPONENT),
          "capetown" => get_string("capetown", constants::M_COMPONENT),
          "bahrain" => get_string("bahrain", constants::M_COMPONENT),
           "ottawa" => get_string("ottawa", constants::M_COMPONENT),
           "frankfurt" => get_string("frankfurt", constants::M_COMPONENT),
           "london" => get_string("london", constants::M_COMPONENT),
           "saopaulo" => get_string("saopaulo", constants::M_COMPONENT),
           "singapore" => get_string("singapore", constants::M_COMPONENT),
            "mumbai" => get_string("mumbai", constants::M_COMPONENT),
        ];
    }



    public static function get_timelimit_options() {
        return [
            0 => get_string("notimelimit", constants::M_COMPONENT),
            30 => get_string("xsecs", constants::M_COMPONENT, '30'),
            45 => get_string("xsecs", constants::M_COMPONENT, '45'),
            60 => get_string("onemin", constants::M_COMPONENT),
            90 => get_string("oneminxsecs", constants::M_COMPONENT, '30'),
            120 => get_string("xmins", constants::M_COMPONENT, '2'),
            150 => get_string("xminsecs", constants::M_COMPONENT, ['minutes' => 2, 'seconds' => 30]),
            180 => get_string("xmins", constants::M_COMPONENT, '3'),
        ];
    }

    // Insert spaces in between segments in order to create "words"
    public static function segment_japanese($passage) {
        $segments = \mod_minilesson\jp\Analyzer::segment($passage);
        return implode(" ", $segments);
    }

    // convert a phrase or word to a series of phonetic characters that we can use to compare text/spoken
    // the segments will usually just return the phrase , but in japanese we want to segment into words
    public static function fetch_phones_and_segments($phrase, $language, $region='tokyo', $segmented=true) {
        global $CFG;

        // first we check if the phrase is segmented with a pipe
        // if we have a pipe prompt = array[0] and response = array[1]
        $phrasebits = explode('|', $phrase);
        if (count($phrasebits) > 1) {
            $phrase = trim($phrasebits[1]);
        }

        switch($language){
            case constants::M_LANG_ENUS:
            case constants::M_LANG_ENAB:
            case constants::M_LANG_ENAU:
            case constants::M_LANG_ENNZ:
            case constants::M_LANG_ENZA:
            case constants::M_LANG_ENIN:
            case constants::M_LANG_ENIE:
            case constants::M_LANG_ENWL:
            case constants::M_LANG_ENGB:
                $phrasebits = explode(' ', $phrase);
                $phonebits = [];
                foreach($phrasebits as $phrasebit){
                    $phonebits[] = metaphone($phrasebit);
                }
                if($segmented) {
                    $phonetic = implode(' ', $phonebits);
                    $segments = $phrase;
                }else {
                    $phonetic = implode('', $phonebits);
                    $segments = $phrase;
                }
                $phonesandsegments = [$phonetic, $segments];
                // the resulting phonetic string will look like this: 0S IS A TK IT IS A KT WN TW 0T IS A MNK
                // but "one" and "won" result in diff phonetic strings and non english support is not there so
                // really we want to put an IPA database on services server and poll as we do for katakanify
                // see: https://github.com/open-dict-data/ipa-dict
                // and command line searchable dictionaries https://github.com/open-dsl-dict/ipa-dict-dsl based on those
                // gdcl :    https://github.com/dohliam/gdcl
                break;
            case constants::M_LANG_JAJP:

                // fetch katakana/hiragana if the JP
                $katakanifyurl = self::fetch_lang_server_url($region, 'katakanify');

                // results look like this:

                /*
                    {
                        "status": true,
                        "message": "Katakanify complete.",
                        "data": {
                            "status": true,
                            "results": [
                                "元気な\t形容詞,*,ナ形容詞,ダ列基本連体形,元気だ,げんきな,代表表記:元気だ/げんきだ",
                                "男の子\t名詞,普通名詞,*,*,男の子,おとこのこ,代表表記:男の子/おとこのこ カテゴリ:人 ドメイン:家庭・暮らし",
                                "は\t助詞,副助詞,*,*,は,は,連語",
                                "いい\t動詞,*,子音動詞ワ行,基本連用形,いう,いい,連語",
                                "こ\t接尾辞,動詞性接尾辞,カ変動詞,未然形,くる,こ,連語",
                                "です\t判定詞,*,判定詞,デス列基本形,だ,です,連語",
                                "。\t特殊,句点,*,*,。,。,連語",
                                "EOS",
                                ""
                            ]
                        }
                    }
                */

                // for Japanese we want to segment it into "words"
                // $passage = utils::segment_japanese($phrase);

                // First check if the phrase is in our cache
                // TO DO make a proper cache definition ...https://docs.moodle.org/dev/Cache_API#Getting_a_cache_object
                // fails on Japanese sometimes .. error unserialising on $cache->get .. which kills modal form submission
                $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'jpphrases');
                $phrasekey = sha1($phrase);
                try {
                    $phonesandsegments = $cache->get($phrasekey);
                }catch(\Exception $e){
                    // fails on japanese for some reason, but we cant dwell on it,
                    $phonesandsegments = false;
                }
                // if we have phones and segments cached, yay
                if($phonesandsegments){
                    return $phonesandsegments;
                }

                // send out for the phonetic processing for japanese text
                // turn numbers into hankaku first // this could be skipped possibly
                // transcripts are usually hankaku but phonetics shouldnt be different either way
                // except they seem to come back as numbers if zenkaku which is better than ni ni for 22
                $phrase = mb_convert_kana($phrase, "n");
                $postdata = ['passage' => $phrase];
                $results = self::curl_fetch($katakanifyurl, $postdata, 'post');
                if(!self::is_json($results)){return false;
                }

                $jsonresults = json_decode($results);
                $nodes = [];
                $words = [];
                if($jsonresults && $jsonresults->status == true){
                    foreach($jsonresults->data->results as $result){
                        $bits = preg_split("/\t+/", $result);
                        if(count($bits) > 1) {
                            $nodes[] = $bits[1];
                            $words[] = $bits[0];
                        }
                    }
                }

                // process nodes
                $katakanaarray = [];
                $segmentarray = [];
                $nodeindex = -1;
                foreach ($nodes as $n) {
                    $nodeindex++;
                    $analysis = explode(',', $n);
                    if(count($analysis) > 5) {
                        switch($analysis[0]) {
                            case '記号':
                                $segmentcount = count($segmentarray);
                                if($segmentcount > 0){
                                    $segmentarray[$segmentcount - 1] .= $words[$nodeindex];
                                }
                                break;
                            default:
                                $reading = '*';
                                if(count($analysis) > 7) {
                                    $reading = $analysis[7];
                                }
                                if ($reading != '*') {
                                    $katakanaarray[] = $reading;
                                } else if($analysis[1] == '数'){
                                    // numbers dont get phoneticized
                                    $katakanaarray[] = $words[$nodeindex];
                                }
                                $segmentarray[] = $words[$nodeindex];
                        }
                    }
                }
                if($segmented) {
                    $phonetic = implode(' ', $katakanaarray);
                    $segments = implode(' ', $segmentarray);
                }else {
                    $phonetic = implode('', $katakanaarray);
                    $segments = implode('', $segmentarray);
                }
                // cache results, so the same data coming again returns faster and saves traffic
                $phonesandsegments = [$phonetic, $segments];
                $cache->set($phrasekey, $phonesandsegments );
                break;

            default:
                $phonetic = '';
                $segments = $phrase;
                $phonesandsegments = [$phonetic, $segments];
        }
        return $phonesandsegments;
    }

    // fetch lang server url, services incl. 'transcribe' , 'lm', 'lt', 'spellcheck', 'katakanify'
    public static function fetch_lang_server_url($region, $service='transcribe') {
        switch($region) {
            case 'useast1':
                $ret = 'https://useast.ls.poodll.com/';
                break;
            default:
                $ret = 'https://' . $region . '.ls.poodll.com/';
        }
        return $ret . $service;
    }

    public static function fetch_options_reportstable() {
        $options = [constants::M_USE_DATATABLES => get_string("reporttableajax", constants::M_COMPONENT),
            constants::M_USE_PAGEDTABLES => get_string("reporttablepaged", constants::M_COMPONENT)];
        return $options;
    }

    public static function fetch_options_transcribers() {
        $options = [constants::TRANSCRIBER_AUTO => get_string("transcriber_auto", constants::M_COMPONENT),
            constants::TRANSCRIBER_POODLL => get_string("transcriber_poodll", constants::M_COMPONENT)];
        return $options;
    }

    public static function fetch_options_finishscreen() {
        $options = [constants::FINISHSCREEN_SIMPLE => get_string("finishscreen_simple", constants::M_COMPONENT),
            constants::FINISHSCREEN_FULL => get_string("finishscreen_full", constants::M_COMPONENT),
            constants::FINISHSCREEN_CUSTOM => get_string("finishscreen_custom", constants::M_COMPONENT),
           ];
        return $options;
    }

    public static function fetch_options_animations() {
        return [
            constants::M_ANIM_FANCY => get_string('anim_fancy', constants::M_COMPONENT),
            constants::M_ANIM_PLAIN => get_string('anim_plain', constants::M_COMPONENT)];
    }

    public static function fetch_options_textprompt() {
        $options = [constants::TEXTPROMPT_DOTS => get_string("textprompt_dots", constants::M_COMPONENT),
                constants::TEXTPROMPT_WORDS => get_string("textprompt_words", constants::M_COMPONENT)];
        return $options;
    }

    public static function fetch_options_yesno() {
        $yesnooptions = [1 => get_string('yes'), 0 => get_string('no')];
        return $yesnooptions;
    }

    public static function fetch_options_listenorread() {
        $options = [constants::LISTENORREAD_READ => get_string("listenorread_read", constants::M_COMPONENT),
                constants::LISTENORREAD_LISTEN => get_string("listenorread_listen", constants::M_COMPONENT),
                constants::LISTENORREAD_LISTENANDREAD => get_string("listenorread_listenandread", constants::M_COMPONENT),
                constants::LISTENORREAD_IMAGE => get_string("listenorread_image", constants::M_COMPONENT)];
        return $options;
    }


    public static function fetch_pagelayout_options() {
        $options = [
                'standard' => 'incourse',
                'embedded' => 'embedded',
                'popup' => 'popup',
        ];
        return $options;
    }


    public static function pack_ttspassageopts($data) {
        $opts = new \stdClass();
        // This is probably over caution, but just in case the data comes in wrong, we want to fall back on something
        if (isset($data->{constants::TTSPASSAGEVOICE})) {
            $opts->{constants::TTSPASSAGEVOICE} = $data->{constants::TTSPASSAGEVOICE};
            $opts->{constants::TTSPASSAGESPEED} = $data->{constants::TTSPASSAGESPEED};
        }else{
            $opts->{constants::TTSPASSAGEVOICE} = 'Salli';
            $opts->{constants::TTSPASSAGESPEED} = constants::TTS_NORMAL;
        }
        $optsjson = json_encode($opts);
        return $optsjson;
    }

    public static function unpack_ttspassageopts($data) {
        if(!self::is_json($data->{constants::TTSPASSAGEOPTS})){return $data;
        }
        $opts = json_decode($data->{constants::TTSPASSAGEOPTS});

        // Overcaution follows ....
        if(isset($opts->{constants::TTSPASSAGESPEED})) {
            $data->{constants::TTSPASSAGESPEED} = $opts->{constants::TTSPASSAGESPEED};
        }else{
            $data->{constants::TTSPASSAGESPEED} = false;
        }
        if(isset($opts->{constants::TTSPASSAGEVOICE})) {
            $data->{constants::TTSPASSAGEVOICE} = $opts->{constants::TTSPASSAGEVOICE};
        }else{
            $data->{constants::TTSPASSAGEVOICE} = "Salli";
        }

        return $data;
    }
    public static function pack_ttsdialogopts($data) {
        $opts = new \stdClass();
        // more overcaution
        if(isset($opts->{constants::TTSDIALOGVISIBLE})) {
            $opts->{constants::TTSDIALOGVISIBLE} = $data->{constants::TTSDIALOGVISIBLE};
        }else{
            $opts->{constants::TTSDIALOGVISIBLE} = false;
        }
        // loop through A,B and C slots and put the data together
        $voiceslots = [constants::TTSDIALOGVOICEA, constants::TTSDIALOGVOICEB, constants::TTSDIALOGVOICEC];
        foreach($voiceslots as $slot){
            if(isset($data->{$slot})){
                $opts->{$slot} = $data->{$slot};
            }else{
                $opts->{$slot} = "Salli";
            }
        }

        $optsjson = json_encode($opts);
        return $optsjson;
    }
    public static function unpack_ttsdialogopts($data) {
        if(!self::is_json($data->{constants::TTSDIALOGOPTS})){return $data;
        }
        // more overcaution
        $opts = json_decode($data->{constants::TTSDIALOGOPTS});
        if(isset($opts->{constants::TTSDIALOGVISIBLE})) {
            $data->{constants::TTSDIALOGVISIBLE} = $opts->{constants::TTSDIALOGVISIBLE};
        }else{
            $data->{constants::TTSDIALOGVISIBLE} = false;
        }

        // loop through A,B and C slots and put the data together
        $voiceslots = [constants::TTSDIALOGVOICEA, constants::TTSDIALOGVOICEB, constants::TTSDIALOGVOICEC];
        foreach($voiceslots as $slot){
            if(isset($opts->{$slot})){
                $data->{$slot} = $opts->{$slot};
            }else{
                $data->{$slot} = "Salli";
            }
        }

        return $data;
    }

    public static function split_into_words($thetext) {
        $thetext = preg_replace('/\s+/', ' ', trim($thetext));
        if($thetext == ''){
            return [];
        }
        return explode(' ', $thetext);
    }

    public static function split_into_sentences($thetext) {
        $thetext = preg_replace('/\s+/', ' ', self::super_trim($thetext));
        if($thetext == ''){
            return [];
        }
        preg_match_all('/([^\.!\?]+[\.!\?"\']+)|([^\.!\?"\']+$)/', $thetext, $matches);
        return $matches[0];
    }

    public static function fetch_auto_voice($langcode) {
        $showall = false;
        $voices = self::get_tts_voices($langcode, $showall);
        $autoindex = array_rand($voices);
        return $voices[$autoindex];
    }

    // can speak neural?
    public static function can_speak_neural($voice, $region) {
        // check if the region is supported
        switch($region){
            case "useast1":
            case "tokyo":
            case "sydney":
            case "dublin":
            case "ottawa":
            case "capetown":
            case "frankfurt":
            case "london":
            case "singapore":
            case "mumbai":
                // ok
                break;
            default:
                return false;
        }

        // check if the voice is supported
        if(in_array($voice, constants::M_NEURALVOICES)){
            return true;
        }else{
            return false;
        }
    }

    public static function get_relevance_options() {
        $ret = [
            constants::RELEVANCETYPE_NONE => get_string('relevancetype_none', constants::M_COMPONENT),
            constants::RELEVANCETYPE_QUESTION => get_string('relevancetype_question', constants::M_COMPONENT),
            constants::RELEVANCETYPE_MODELANSWER => get_string('relevancetype_modelanswer', constants::M_COMPONENT),
        ];
        return $ret;
    }

    public static function get_tts_options($nossml=false) {
        $ret = [constants::TTS_NORMAL => get_string('ttsnormal', constants::M_COMPONENT),
                constants::TTS_SLOW => get_string('ttsslow', constants::M_COMPONENT),
                constants::TTS_VERYSLOW => get_string('ttsveryslow', constants::M_COMPONENT)];
        if(!$nossml){$ret += [constants::TTS_SSML => get_string('ttsssml', constants::M_COMPONENT)];
        }
        return $ret;
    }

    public static function get_tts_voices($langcode, $showall) {
        $alllang = constants::ALL_VOICES;

        if(array_key_exists($langcode, $alllang) && !$showall) {
            return $alllang[$langcode];
        }else if($showall) {
            $usearray = [];

            // add current language first (in some cases there is no TTS for the lang)
            if(isset($alllang[$langcode])) {
                foreach ($alllang[$langcode] as $v => $thevoice) {
                    $neuraltag = in_array($v, constants::M_NEURALVOICES) ? ' (+)' : '';
                    $usearray[$v] = get_string(strtolower($langcode), constants::M_COMPONENT) . ': ' . $thevoice . $neuraltag;
                }
            }
            // then all the rest
            foreach($alllang as $lang => $voices){
                if($lang == $langcode){continue;
                }
                foreach($voices as $v => $thevoice){
                    $neuraltag = in_array($v, constants::M_NEURALVOICES) ? ' (+)' : '';
                    $usearray[$v] = get_string(strtolower($lang), constants::M_COMPONENT) . ': ' . $thevoice . $neuraltag;
                }
            }
            return $usearray;
        }else{
                return $alllang[constants::M_LANG_ENUS];
        }
    }

    public static function get_lang_options() {
        return [
               constants::M_LANG_ARAE => get_string('ar-ae', constants::M_COMPONENT),
               constants::M_LANG_ARSA => get_string('ar-sa', constants::M_COMPONENT),
               constants::M_LANG_EUES => get_string('eu-es', constants::M_COMPONENT),
               constants::M_LANG_BGBG => get_string('bg-bg', constants::M_COMPONENT),
                constants::M_LANG_HRHR => get_string('hr-hr', constants::M_COMPONENT),
               constants::M_LANG_ZHCN => get_string('zh-cn', constants::M_COMPONENT),
               constants::M_LANG_CSCZ => get_string('cs-cz', constants::M_COMPONENT),
               constants::M_LANG_DADK => get_string('da-dk', constants::M_COMPONENT),
               constants::M_LANG_NLNL => get_string('nl-nl', constants::M_COMPONENT),
               constants::M_LANG_NLBE => get_string('nl-be', constants::M_COMPONENT),
               constants::M_LANG_ENUS => get_string('en-us', constants::M_COMPONENT),
               constants::M_LANG_ENGB => get_string('en-gb', constants::M_COMPONENT),
               constants::M_LANG_ENAU => get_string('en-au', constants::M_COMPONENT),
               constants::M_LANG_ENNZ => get_string('en-nz', constants::M_COMPONENT),
               constants::M_LANG_ENZA => get_string('en-za', constants::M_COMPONENT),
               constants::M_LANG_ENIN => get_string('en-in', constants::M_COMPONENT),
               constants::M_LANG_ENIE => get_string('en-ie', constants::M_COMPONENT),
               constants::M_LANG_ENWL => get_string('en-wl', constants::M_COMPONENT),
               constants::M_LANG_ENAB => get_string('en-ab', constants::M_COMPONENT),
               constants::M_LANG_FAIR => get_string('fa-ir', constants::M_COMPONENT),
               constants::M_LANG_FILPH => get_string('fil-ph', constants::M_COMPONENT),
                constants::M_LANG_FIFI => get_string('fi-fi', constants::M_COMPONENT),
               constants::M_LANG_FRCA => get_string('fr-ca', constants::M_COMPONENT),
               constants::M_LANG_FRFR => get_string('fr-fr', constants::M_COMPONENT),
               constants::M_LANG_DEDE => get_string('de-de', constants::M_COMPONENT),
               constants::M_LANG_DEAT => get_string('de-at', constants::M_COMPONENT),
               constants::M_LANG_DECH => get_string('de-ch', constants::M_COMPONENT),
               constants::M_LANG_HIIN => get_string('hi-in', constants::M_COMPONENT),
                constants::M_LANG_ELGR => get_string('el-gr', constants::M_COMPONENT),
               constants::M_LANG_HEIL => get_string('he-il', constants::M_COMPONENT),
               constants::M_LANG_HUHU => get_string('hu-hu', constants::M_COMPONENT),
               constants::M_LANG_IDID => get_string('id-id', constants::M_COMPONENT),
                constants::M_LANG_ISIS => get_string('is-is', constants::M_COMPONENT),
               constants::M_LANG_ITIT => get_string('it-it', constants::M_COMPONENT),
               constants::M_LANG_JAJP => get_string('ja-jp', constants::M_COMPONENT),
               constants::M_LANG_KOKR => get_string('ko-kr', constants::M_COMPONENT),
               constants::M_LANG_LTLT => get_string('lt-lt', constants::M_COMPONENT),
               constants::M_LANG_LVLV => get_string('lv-lv', constants::M_COMPONENT),
               constants::M_LANG_MINZ => get_string('mi-nz', constants::M_COMPONENT),
               constants::M_LANG_MSMY => get_string('ms-my', constants::M_COMPONENT),
                constants::M_LANG_MKMK => get_string('mk-mk', constants::M_COMPONENT),
                constants::M_LANG_NONO => get_string('no-no', constants::M_COMPONENT),
                constants::M_LANG_PLPL => get_string('pl-pl', constants::M_COMPONENT),
               constants::M_LANG_PTBR => get_string('pt-br', constants::M_COMPONENT),
               constants::M_LANG_PTPT => get_string('pt-pt', constants::M_COMPONENT),
                constants::M_LANG_RORO => get_string('ro-ro', constants::M_COMPONENT),
               constants::M_LANG_RURU => get_string('ru-ru', constants::M_COMPONENT),
                constants::M_LANG_ESUS => get_string('es-us', constants::M_COMPONENT),
                constants::M_LANG_ESES => get_string('es-es', constants::M_COMPONENT),
               constants::M_LANG_SKSK => get_string('sk-sk', constants::M_COMPONENT),
               constants::M_LANG_SLSI => get_string('sl-si', constants::M_COMPONENT),
               constants::M_LANG_SRRS => get_string('sr-rs', constants::M_COMPONENT),
               constants::M_LANG_SVSE => get_string('sv-se', constants::M_COMPONENT),
               constants::M_LANG_TAIN => get_string('ta-in', constants::M_COMPONENT),
               constants::M_LANG_TEIN => get_string('te-in', constants::M_COMPONENT),
               constants::M_LANG_TRTR => get_string('tr-tr', constants::M_COMPONENT),
               constants::M_LANG_UKUA => get_string('uk-ua', constants::M_COMPONENT),
               constants::M_LANG_VIVN => get_string('vi-vn', constants::M_COMPONENT),

        ];
    }

    public static function get_prompttype_options() {
        return [
                constants::M_PROMPT_SEPARATE => get_string('prompt-separate', constants::M_COMPONENT),
                constants::M_PROMPT_RICHTEXT => get_string('prompt-richtext', constants::M_COMPONENT),
        ];

    }

    public static function get_containerwidth_options() {
        return [
            constants::M_CONTWIDTH_COMPACT => get_string('contwidth-compact', constants::M_COMPONENT),
            constants::M_CONTWIDTH_WIDE => get_string('contwidth-wide', constants::M_COMPONENT),
            constants::M_CONTWIDTH_FULL => get_string('contwidth-full', constants::M_COMPONENT),
        ];

    }

    public static function add_mform_elements($mform, $context, $cmid, $setuptab=false) {
        global $CFG, $COURSE;
        $dateoptions = ['optional' => true];
        $config = get_config(constants::M_COMPONENT);

        // if this is setup tab we need to add a field to tell it the id of the activity
        if($setuptab) {
            $mform->addElement('hidden', 'n');
            $mform->setType('n', PARAM_INT);
        }

        // -------------------------------------------------------------------------------
        // Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('minilessonname', constants::M_COMPONENT), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'minilessonname', constants::M_COMPONENT);

        // Adding the standard "intro" and "introformat" fields
        // we do not support this in tabs
        if(!$setuptab) {
            $label = get_string('moduleintro');
            $mform->addElement('editor', 'introeditor', $label, ['rows' => 10], ['maxfiles' => EDITOR_UNLIMITED_FILES,
                    'noclean' => true, 'context' => $context, 'subdirs' => true]);
            $mform->setType('introeditor', PARAM_RAW); // no XSS prevention here, users must be trusted
            $mform->addElement('advcheckbox', 'showdescription', get_string('showdescription'));
            $mform->addHelpButton('showdescription', 'showdescription');
        }

        // page layout options
        $layoutoptions = self::fetch_pagelayout_options();
        $mform->addElement('select', 'pagelayout', get_string('pagelayout', constants::M_COMPONENT), $layoutoptions);
        $mform->setDefault('pagelayout', 'standard');

        // time target
        $mform->addElement('hidden', 'timelimit', 0);
        $mform->setType('timelimit', PARAM_INT);

        /*
         * Later can add a proper time limit
                $timelimit_options = \mod_minilesson\utils::get_timelimit_options();
                $mform->addElement('select', 'timelimit', get_string('timelimit', constants::M_COMPONENT),
                    $timelimit_options);
                $mform->setDefault('timelimit',60);
        */

        // add other editors
        // could add files but need the context/mod info. So for now just rich text
        $config = get_config(constants::M_COMPONENT);

        // The passage
        // $edfileoptions = minilesson_editor_with_files_options($this->context);
        $ednofileoptions = minilesson_editor_no_files_options($context);
        $opts = ['rows' => '15', 'columns' => '80'];

        // welcome message [just kept cos its a pain in the butt to do this again from scratch if we ever do]
        /*
        $opts = array('rows'=>'6', 'columns'=>'80');
        $mform->addElement('editor','welcome_editor',get_string('welcomelabel',constants::M_COMPONENT),$opts, $ednofileoptions);
        $mform->setDefault('welcome_editor',array('text'=>$config->defaultwelcome, 'format'=>FORMAT_MOODLE));
        $mform->setType('welcome_editor',PARAM_RAW);
        */

        // showq titles
        $yesnooptions = [1 => get_string('yes'), 0 => get_string('no')];
        $mform->addElement('select', 'showqtitles', get_string('showqtitles', constants::M_COMPONENT), $yesnooptions);
        $mform->setDefault('showqtitles', 0);

        // Show item review.
        $mform->addElement('select', 'showitemreview', get_string('showitemreview', constants::M_COMPONENT), $yesnooptions);
        $mform->addHelpButton('showitemreview', 'showitemreview', constants::M_COMPONENT);
        $mform->setDefault('showitemreview', $config->showitemreview);

        // Attempts
        $attemptoptions = [0 => get_string('unlimited', constants::M_COMPONENT),
                1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', ];
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', constants::M_COMPONENT), $attemptoptions);

        // tts options
        $langoptions = self::get_lang_options();
        $mform->addElement('select', 'ttslanguage', get_string('ttslanguage', constants::M_COMPONENT), $langoptions);
        $mform->setDefault('ttslanguage', $config->ttslanguage);

        // transcriber
        $toptions = self::fetch_options_transcribers();
        $mform->addElement('select', 'transcriber', get_string('transcriber', constants::M_COMPONENT),
            $toptions, $config->transcriber);

        // region
        $regionoptions = self::get_region_options();
        $mform->addElement('select', 'region', get_string('awsregion', constants::M_COMPONENT), $regionoptions);
        $mform->setDefault('region', $config->awsregion);

        // prompt types
        $prompttypes = self::get_prompttype_options();
        $mform->addElement('select', 'richtextprompt', get_string('prompttype', constants::M_COMPONENT), $prompttypes);
        $mform->addHelpButton('richtextprompt', 'prompttype', constants::M_COMPONENT);
        $mform->setDefault('richtextprompt', $config->prompttype);

        // advanced
        $name = 'advanced';
        $label = get_string($name, 'minilesson');
        $mform->addElement('header', $name, $label);
        $mform->setExpanded($name, false);

        // Adding the lessonfont field
        $mform->addElement('text', 'lessonfont', get_string('lessonfont', constants::M_COMPONENT), ['size' => '64']);
        $mform->addHelpButton('lessonfont', 'lessonfont', constants::M_COMPONENT);
        $mform->setType('lessonfont', PARAM_TEXT);

        // Add csskey
        $mform->addElement('text', 'csskey', get_string('csskey', constants::M_COMPONENT), ['size' => '8']);
        $mform->setType('csskey', PARAM_TEXT);
        $mform->setDefault('csskey', '');
        $mform->addHelpButton('csskey', 'csskey', constants::M_COMPONENT);

        // Add passagekey
        $mform->addElement('text', 'lessonkey', get_string('lessonkey', constants::M_COMPONENT), ['size' => '8']);
        $mform->setType('lessonkey', PARAM_TEXT);
        $mform->setDefault('lessonkey', '');
        $mform->addHelpButton('lessonkey', 'lessonkey', constants::M_COMPONENT);

        // container width
        $widthoptions = self::get_containerwidth_options();
        $mform->addElement('select', 'containerwidth', get_string('containerwidth', constants::M_COMPONENT), $widthoptions);
        $mform->addHelpButton('containerwidth', 'containerwidth', constants::M_COMPONENT);
        $mform->setDefault('containerwidth', $config->containerwidth);

        // finishscreen
        $screenoptions = self::fetch_options_finishscreen();
        $mform->addElement('select', 'finishscreen', get_string('finishscreen', constants::M_COMPONENT), $screenoptions);
        $mform->addHelpButton('finishscreen', 'finishscreen', constants::M_COMPONENT);
        $mform->setDefault('finishscreen', $config->finishscreen);

        // custom finish screen
        $mform->addElement('textarea', 'finishscreencustom', get_string('finishscreencustom', constants::M_COMPONENT), ['wrap' => 'virtual', 'style' => 'width: 100%;']);
        $mform->addHelpButton('finishscreencustom', 'finishscreencustom', constants::M_COMPONENT);
        $mform->setType('finishscreencustom', PARAM_RAW);
        $mform->HideIf('finishscreencustom', 'finishscreen', 'neq', constants::FINISHSCREEN_CUSTOM);
        $mform->setDefault('finishscreencustom', $config->finishscreencustom);

        // activity opens closes
        $name = 'activityopenscloses';
        $label = get_string($name, 'minilesson');
        $mform->addElement('header', $name, $label);
        $mform->setExpanded($name, false);
        // -----------------------------------------------------------------------------

        $name = 'viewstart';
        $label = get_string($name, "minilesson");
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name, constants::M_COMPONENT);

        $name = 'viewend';
        $label = get_string($name, "minilesson");
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name, constants::M_COMPONENT);

        // Post attempt
        // Get the modules.
        if(!$setuptab) {
            if ($mods = get_course_mods($COURSE->id)) {

                $mform->addElement('header', 'postattemptheader', get_string('postattemptheader', constants::M_COMPONENT));

                $modinstances = [];
                foreach ($mods as $mod) {
                    // Get the module name and then store it in a new array.
                    if ($module = get_coursemodule_from_instance($mod->modname, $mod->instance, $COURSE->id)) {
                        // Exclude this MiniLesson activity (if it's already been saved.)
                        if (!$cmid || $cmid != $mod->id) {
                            $modinstances[$mod->id] = $mod->modname . ' - ' . $module->name;
                        }
                    }
                }
                asort($modinstances); // Sort by module name.
                $modinstances = [0 => get_string('none')] + $modinstances;

                $mform->addElement('select', 'activitylink', get_string('activitylink', 'lesson'), $modinstances);
                $mform->addHelpButton('activitylink', 'activitylink', 'lesson');
                $mform->setDefault('activitylink', 0);
            }
        }

    } //end of add_mform_elements

    public static function prepare_file_and_json_stuff($moduleinstance, $modulecontext) {

        $ednofileoptions = minilesson_editor_no_files_options($modulecontext);
        $editors  = minilesson_get_editornames();

        $itemid = 0;
        foreach($editors as $editor){
            $moduleinstance = file_prepare_standard_editor((object)$moduleinstance, $editor, $ednofileoptions, $modulecontext, constants::M_COMPONENT, $editor, $itemid);
        }

        return $moduleinstance;

    }//end of prepare_file_and_json_stuff

    public static function clean_ssml_chars($speaktext) {
        // deal with SSML reserved characters
        $speaktext = str_replace("&", "&amp;", $speaktext);
        $speaktext = str_replace("'", "&apos;", $speaktext);
        $speaktext = str_replace('"', "&quot;", $speaktext);
        $speaktext = str_replace("<", "&lt;", $speaktext);
        $speaktext = str_replace(">", "&gt;", $speaktext);
        return $speaktext;
    }

    // fetch the MP3 URL of the text we want read aloud
    public static function fetch_polly_url($token, $region, $speaktext, $voiceoption, $voice) {
        global $USER;

        $texttype = 'ssml';
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'polly');
        $key = sha1($speaktext . '|' . $texttype . '|' . $voice);
        $pollyurl = $cache->get($key);
        if($pollyurl && !empty($pollyurl)){
            return $pollyurl;
        }

        switch((int)($voiceoption)){

            // slow
            case 1:
                // fetch slightly slower version of speech
                // rate = 'slow' or 'x-slow' or 'medium'
                $speaktext = self::clean_ssml_chars($speaktext);
                $speaktext = '<speak><break time="1000ms"></break><prosody rate="slow">' . $speaktext . '</prosody></speak>';
                break;
            // veryslow
            case 2:
                // fetch slightly slower version of speech
                // rate = 'slow' or 'x-slow' or 'medium'
                $speaktext = self::clean_ssml_chars($speaktext);
                $speaktext = '<speak><break time="1000ms"></break><prosody rate="x-slow">' . $speaktext . '</prosody></speak>';
                break;
            // ssml
            case 3:
                $speaktext = '<speak>' . $speaktext . '</speak>';
                break;

            // normal
            case 0:
            default:
                // fetch slightly slower version of speech
                // rate = 'slow' or 'x-slow' or 'medium'
                $speaktext = self::clean_ssml_chars($speaktext);
                $speaktext = '<speak><break time="1000ms"></break>' . $speaktext . '</speak>';
                break;

        }

        // The REST API we are calling
        $functionname = 'local_cpapi_fetch_polly_url';

        // log.debug(params);
        $params = [];
        $params['wstoken'] = $token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['text'] = urlencode($speaktext);
        $params['texttype'] = $texttype;
        $params['voice'] = $voice;
        $params['appid'] = constants::M_COMPONENT;;
        $params['owner'] = hash('md5', $USER->username);
        $params['region'] = $region;
        $params['engine'] = self::can_speak_neural($voice, $region) ? 'neural' : 'standard';
        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        // returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            // if all good, then lets do the embed
        } else if ($payloadobject->returnCode === 0) {
            $pollyurl = $payloadobject->returnMessage;
            // if its an S3 URL  then we cache it, yay
            if(\core_text::strpos($pollyurl, 'pollyfile.poodll.net') > 0) {
                $cache->set($key, $pollyurl);
            }
            return $pollyurl;
        } else {
            return false;
        }
    }

    public static function fetch_item_from_itemrecord($itemrecord, $moduleinstance, $context=false) {
        // Set up the item type specific parts of the form data
        switch($itemrecord->type){
            case constants::TYPE_MULTICHOICE:
                return new local\itemtype\item_multichoice($itemrecord, $moduleinstance, $context);
            case constants::TYPE_MULTIAUDIO:
                return new local\itemtype\item_multiaudio($itemrecord, $moduleinstance, $context);
            case constants::TYPE_DICTATIONCHAT:
                return new local\itemtype\item_dictationchat($itemrecord, $moduleinstance, $context);
            case constants::TYPE_DICTATION:
                return new local\itemtype\item_dictation($itemrecord, $moduleinstance, $context);
            case constants::TYPE_SPEECHCARDS:
                return new local\itemtype\item_speechcards($itemrecord, $moduleinstance, $context);
            case constants::TYPE_LISTENREPEAT:
                return new local\itemtype\item_listenrepeat($itemrecord, $moduleinstance, $context);
            case constants::TYPE_PAGE:
                return new local\itemtype\item_page($itemrecord, $moduleinstance, $context);
            case constants::TYPE_SMARTFRAME:
                return new local\itemtype\item_smartframe($itemrecord, $moduleinstance, $context);
            case constants::TYPE_SHORTANSWER:
                return new local\itemtype\item_shortanswer($itemrecord, $moduleinstance, $context);
            case constants::TYPE_SGAPFILL:
                return new local\itemtype\item_speakinggapfill($itemrecord, $moduleinstance, $context);
            case constants::TYPE_LGAPFILL:
                return new local\itemtype\item_listeninggapfill($itemrecord, $moduleinstance, $context);
            case constants::TYPE_TGAPFILL:
                return new local\itemtype\item_typinggapfill($itemrecord, $moduleinstance, $context);
            case constants::TYPE_COMPQUIZ:
                return new local\itemtype\item_compquiz($itemrecord, $moduleinstance, $context);
            case constants::TYPE_BUTTONQUIZ:
                return new local\itemtype\item_buttonquiz($itemrecord, $moduleinstance, $context);
            case constants::TYPE_SPACEGAME:
                return new local\itemtype\item_spacegame($itemrecord, $moduleinstance, $context);
            case constants::TYPE_FREEWRITING:
                return new local\itemtype\item_freewriting($itemrecord, $moduleinstance, $context);
            case constants::TYPE_FREESPEAKING:
                return new local\itemtype\item_freespeaking($itemrecord, $moduleinstance, $context);
            case constants::TYPE_FLUENCY:
                return new local\itemtype\item_fluency($itemrecord, $moduleinstance, $context);
            case constants::TYPE_PASSAGEREADING:
                return new local\itemtype\item_passagereading($itemrecord, $moduleinstance, $context);
            case constants::TYPE_CONVERSATION:
                return new local\itemtype\item_conversation($itemrecord, $moduleinstance, $context);
            default:
        }
    }


    public static function fetch_itemform_classname($itemtype) {
        // Fetch the correct form
        switch($itemtype){
            case constants::TYPE_MULTICHOICE:
                return '\\'. constants::M_COMPONENT . '\local\itemform\multichoiceform';
            case constants::TYPE_MULTIAUDIO:
                return '\\'. constants::M_COMPONENT . '\local\itemform\multiaudioform';
            case constants::TYPE_DICTATIONCHAT:
                return '\\'. constants::M_COMPONENT . '\local\itemform\dictationchatform';
            case constants::TYPE_DICTATION:
                return '\\'. constants::M_COMPONENT . '\local\itemform\dictationform';
            case constants::TYPE_SPEECHCARDS:
                return '\\'. constants::M_COMPONENT . '\local\itemform\speechcardsform';
            case constants::TYPE_LISTENREPEAT:
                return '\\'. constants::M_COMPONENT . '\local\itemform\listenrepeatform';
            case constants::TYPE_PAGE:
                return '\\'. constants::M_COMPONENT . '\local\itemform\pageform';
            case constants::TYPE_SMARTFRAME:
                return '\\'. constants::M_COMPONENT . '\local\itemform\smartframe';
            case constants::TYPE_SHORTANSWER:
                return '\\'. constants::M_COMPONENT . '\local\itemform\shortanswerform';
            case constants::TYPE_SGAPFILL:
                return '\\'. constants::M_COMPONENT . '\local\itemform\speakinggapfillform';
            case constants::TYPE_LGAPFILL:
                return '\\'. constants::M_COMPONENT . '\local\itemform\listeninggapfillform';
            case constants::TYPE_TGAPFILL:
                return '\\'. constants::M_COMPONENT . '\local\itemform\typinggapfillform';
            case constants::TYPE_COMPQUIZ:
                return '\\'. constants::M_COMPONENT . '\local\itemform\compquizform';
            case constants::TYPE_BUTTONQUIZ:
                return '\\'. constants::M_COMPONENT . '\local\itemform\buttonquizform';
            case constants::TYPE_SPACEGAME:
                return '\\'. constants::M_COMPONENT . '\local\itemform\spacegameform';
            case constants::TYPE_FREEWRITING:
                return '\\'. constants::M_COMPONENT . '\local\itemform\freewritingform';
            case constants::TYPE_FREESPEAKING:
                return '\\'. constants::M_COMPONENT . '\local\itemform\freespeakingform';
            case constants::TYPE_FLUENCY:
                return '\\'. constants::M_COMPONENT . '\local\itemform\fluencyform';
            case constants::TYPE_PASSAGEREADING:
                return '\\'. constants::M_COMPONENT . '\local\itemform\passagereadingform';
            case constants::TYPE_CONVERSATION:
                return '\\'. constants::M_COMPONENT . '\local\itemform\conversationform';
            default:
                return false;
        }
    }

    public static function do_mb_str_split($string, $splitlength = 1, $encoding = null) {
        // for greater than PHP 7.4
        if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
            // Code for PHP 7.4 and above
            return mb_str_split($string, $splitlength, $encoding);
        }

        // for less than PHP 7.4
        if (null !== $string && !\is_scalar($string) && !(\is_object($string) && \method_exists($string, '__toString'))) {
            trigger_error('mb_str_split(): expects parameter 1 to be string, '.\gettype($string).' given', E_USER_WARNING);
            return null;
        }
        if (null !== $splitlength && !\is_bool($splitlength) && !\is_numeric($splitlength)) {
            trigger_error('mb_str_split(): expects parameter 2 to be int, '.\gettype($splitlength).' given', E_USER_WARNING);
            return null;
        }
        $splitlength = (int) $splitlength;
        if (1 > $splitlength) {
            trigger_error('mb_str_split(): The length of each segment must be greater than zero', E_USER_WARNING);
            return false;
        }
        if (null === $encoding) {
            $encoding = mb_internal_encoding();
        } else {
            $encoding = (string) $encoding;
        }

        if (! in_array($encoding, mb_list_encodings(), true)) {
            static $aliases;
            if ($aliases === null) {
                $aliases = [];
                foreach (mb_list_encodings() as $encoding) {
                    $encodingaliases = mb_encoding_aliases($encoding);
                    if ($encodingaliases) {
                        foreach ($encodingaliases as $alias) {
                            $aliases[] = $alias;
                        }
                    }
                }
            }
            if (! in_array($encoding, $aliases, true)) {
                trigger_error('mb_str_split(): Unknown encoding "'.$encoding.'"', E_USER_WARNING);
                return null;
            }
        }

        $result = [];
        $length = mb_strlen($string, $encoding);
        for ($i = 0; $i < $length; $i += $splitlength) {
            $result[] = mb_substr($string, $i, $splitlength, $encoding);
        }
        return $result;
    }

    public static function super_trim($str) {
        if ($str == null) {
            return '';
        } else {
            $str = trim($str);
            return $str;
        }
    }


    /**
     * @param  $moduledata
     * @return mixed
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \require_login_exception
     */
    public static function create_instance($moduledata, $course, $section=1) {
        global $CFG;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir.'/filelib.php');
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->libdir.'/completionlib.php');
        require_once($CFG->libdir.'/plagiarismlib.php');
        require_once($CFG->dirroot . '/course/modlib.php');

        // create new cm
        list($module, $context, $cw, $cm, $data) = prepare_new_moduleinfo_data($course, constants::M_MODNAME, $section);

        // add the module info to our moduledata object
        $moduledata->module = $module->id;
        $moduledata->section = $cw->section;

        // not sure id we need these .. but ok..
        $moduledata->add    = 1;
        $moduledata->update = 0;
        $moduledata->return = 0;
        $moduledata->type = constants::M_MODNAME;
        $moduledata->sectionreturn = null;

        // update module
        $moduledata = add_moduleinfo($moduledata, $course);
        return $moduledata->coursemodule;
    }//end of function
}
