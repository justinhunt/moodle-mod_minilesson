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
 * Utils for poodlltime plugin
 *
 * @package    mod_poodlltime
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 namespace mod_poodlltime;
defined('MOODLE_INTERNAL') || die();

use \mod_poodlltime\constants;


/**
 * Functions used generally across this mod
 *
 * @package    mod_poodlltime
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils{

    //we need to consider legacy client side URLs and cloud hosted ones
    public static function make_audio_URL($filename, $contextid, $component, $filearea, $itemid){
        //we need to consider legacy client side URLs and cloud hosted ones
        if(strpos($filename,'http')===0){
            $ret = $filename;
        }else {
            $ret = \moodle_url::make_pluginfile_url($contextid, $component,
                $filearea,
                $itemid, '/',
                $filename);
        }
        return $ret;
    }

    public static function update_step_grade($cm,$quizresults,$attemptid){

        global $USER, $DB;

        $result=false;
        $message = '';
        $returndata=false;

        $attempt = $DB->get_record(constants::M_USERTABLE,array('id'=>$attemptid,'userid'=>$USER->id));
        if($attempt) {
            $useresults = json_decode($quizresults);
            $answers = $useresults->answers;
            //more data here

            if (isset($answers->{'1'})) { $attempt->qanswer1 = $answers->{'1'}; }
            if (isset($answers->{'2'})) { $attempt->qanswer2 = $answers->{'2'}; }
            if (isset($answers->{'3'})) { $attempt->qanswer3 = $answers->{'3'}; }
            if (isset($answers->{'4'})) { $attempt->qanswer4 = $answers->{'4'}; }
            if (isset($answers->{'5'})) { $attempt->qanswer5 = $answers->{'5'}; }

            //grade quiz results
            $comp_test =  new comprehensiontest($cm);
            $score= $comp_test->grade_test($answers);
            $attempt->qscore = $score;



            $result = $DB->update_record(constants::M_USERTABLE, $attempt);
            if($result) {
                $returndata= '';
            }else{
                $message = 'unable to update attempt record';
            }
        }else{
            $message='no attempt of that id for that user';
        }
        return_to_page($result,$message,$returndata);
}



    //calculate the Error rate
    //see https://www.readinga-z.com/helpful-tools/about-running-records/scoring-a-running-record/
    public static function calc_error_rate($errorcount,$wordcount){
        if($errorcount > 0 && $wordcount > 0) {
            $ret = "1:" . round($wordcount / $errorcount);
        }else if($wordcount > 0){
            $ret = "-:" . $wordcount;
        }else{
            $ret = "-:-";
        }
        return $ret;
    }

    //calculate the Self Correction rate
    //See https://www.readinga-z.com/helpful-tools/about-running-records/scoring-a-running-record/
    public static function calc_sc_rate($errorcount,$sccount){
        if($errorcount > 0 && $sccount > 0) {
            $ret = "1:" . round(($errorcount + $sccount) / $sccount);
        }else if($errorcount > 0){
            $ret = "-:" . $errorcount;
        }else{
            $ret = "-:-";
        }
        return $ret;
    }

    //are we willing and able to transcribe submissions?
    public static function can_transcribe($instance) {

        //we default to true
        //but it only takes one no ....
        $ret = true;

        //The regions that can transcribe
        switch($instance->region){
            default:
                $ret = true;
        }

        //if user disables ai, we do not transcribe
        if (!$instance->enableai) {
            $ret = false;
        }

        return $ret;
    }

    //see if this is truly json or some error
    public static function is_json($string) {
        if(!$string){return false;}
        if(empty($string)){return false;}
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    //we use curl to fetch transcripts from AWS and Tokens from cloudpoodll
    //this is our helper
    public static function curl_fetch($url,$postdata=false)
    {
        global $CFG;

        require_once($CFG->libdir.'/filelib.php');
        $curl = new \curl();

        $result = $curl->get($url, $postdata);
        return $result;
    }

    //This is called from the settings page and we do not want to make calls out to cloud.poodll.com on settings
    //page load, for performance and stability issues. So if the cache is empty and/or no token, we just show a
    //"refresh token" links
    public static function fetch_token_for_display($apiuser,$apisecret){
       global $CFG;

       //First check that we have an API id and secret
        //refresh token
        $refresh = \html_writer::link($CFG->wwwroot . '/mod/poodlltime/refreshtoken.php',
                get_string('refreshtoken',constants::M_COMPONENT)) . '<br>';


        $message = '';
        $apiuser = trim($apiuser);
        $apisecret = trim($apisecret);
        if(empty($apiuser)){
           $message .= get_string('noapiuser',constants::M_COMPONENT) . '<br>';
       }
        if(empty($apisecret)){
            $message .= get_string('noapisecret',constants::M_COMPONENT);
        }

        if(!empty($message)){
            return $refresh . $message;
        }

        //Fetch from cache and process the results and display
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');

        //if we have no token object the creds were wrong ... or something
        if(!($tokenobject)){
            $message = get_string('notokenincache',constants::M_COMPONENT);
            //if we have an object but its no good, creds werer wrong ..or something
        }elseif(!property_exists($tokenobject,'token') || empty($tokenobject->token)){
            $message = get_string('credentialsinvalid',constants::M_COMPONENT);
        //if we do not have subs, then we are on a very old token or something is wrong, just get out of here.
        }elseif(!property_exists($tokenobject,'subs')){
            $message = 'No subscriptions found at all';
        }
        if(!empty($message)){
            return $refresh . $message;
        }

        //we have enough info to display a report. Lets go.
        foreach ($tokenobject->subs as $sub){
            $sub->expiredate = date('d/m/Y',$sub->expiredate);
            $message .= get_string('displaysubs',constants::M_COMPONENT, $sub) . '<br>';
        }
        //Is app authorised
        if(in_array(constants::M_COMPONENT,$tokenobject->apps)){
            $message .= get_string('appauthorised',constants::M_COMPONENT) . '<br>';
        }else{
            $message .= get_string('appnotauthorised',constants::M_COMPONENT) . '<br>';
        }

        return $refresh . $message;

    }

    //We need a Poodll token to make all this recording and transcripts happen
    public static function fetch_token($apiuser, $apisecret, $force=false)
    {

        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');
        $tokenuser = $cache->get('recentpoodlluser');
        $apiuser = trim($apiuser);
        $apisecret = trim($apisecret);
        $now = time();

        //if we got a token and its less than expiry time
        // use the cached one
        if($tokenobject && $tokenuser && $tokenuser==$apiuser && !$force){
            if($tokenobject->validuntil == 0 || $tokenobject->validuntil > $now){
               // $hoursleft= ($tokenobject->validuntil-$now) / (60*60);
                return $tokenobject->token;
            }
        }

        // Send the request & save response to $resp
        $token_url ="https://cloud.poodll.com/local/cpapi/poodlltoken.php";
        $postdata = array(
            'username' => $apiuser,
            'password' => $apisecret,
            'service'=>'cloud_poodll'
        );
        $token_response = self::curl_fetch($token_url,$postdata);
        if ($token_response) {
            $resp_object = json_decode($token_response);
            if($resp_object && property_exists($resp_object,'token')) {
                $token = $resp_object->token;
                //store the expiry timestamp and adjust it for diffs between our server times
                if($resp_object->validuntil) {
                    $validuntil = $resp_object->validuntil - ($resp_object->poodlltime - $now);
                    //we refresh one hour out, to prevent any overlap
                    $validuntil = $validuntil - (1 * HOURSECS);
                }else{
                    $validuntil = 0;
                }

                $tillrefreshhoursleft= ($validuntil-$now) / (60*60);


                //cache the token
                $tokenobject = new \stdClass();
                $tokenobject->token = $token;
                $tokenobject->validuntil = $validuntil;
                $tokenobject->subs=false;
                $tokenobject->apps=false;
                $tokenobject->sites=false;
                if(property_exists($resp_object,'subs')){
                    $tokenobject->subs = $resp_object->subs;
                }
                if(property_exists($resp_object,'apps')){
                    $tokenobject->apps = $resp_object->apps;
                }
                if(property_exists($resp_object,'sites')){
                    $tokenobject->sites = $resp_object->sites;
                }

                $cache->set('recentpoodlltoken', $tokenobject);
                $cache->set('recentpoodlluser', $apiuser);

            }else{
                $token = '';
                if($resp_object && property_exists($resp_object,'error')) {
                    //ERROR = $resp_object->error
                }
            }
        }else{
            $token='';
        }
        return $token;
    }

    //check token and tokenobject(from cache)
    //return error message or blank if its all ok
    public static function fetch_token_error($token){
        global $CFG;

        //check token authenticated
        if(empty($token)) {
            $message = get_string('novalidcredentials', constants::M_COMPONENT,
                    $CFG->wwwroot . constants::M_PLUGINSETTINGS);
            return $message;
        }

        // Fetch from cache and process the results and display.
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');

        //we should not get here if there is no token, but lets gracefully die, [v unlikely]
        if (!($tokenobject)) {
            $message = get_string('notokenincache', constants::M_COMPONENT);
            return $message;
        }

        //We have an object but its no good, creds were wrong ..or something. [v unlikely]
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

        //just return empty if there is no error.
        return '';
    }

    /*
     * Turn a passage with text "lines" into html "brs"
     *
     * @param String The passage of text to convert
     * @param String An optional pad on each replacement (needed for processing when marking up words as spans in passage)
     * @return String The converted passage of text
     */
    public static function lines_to_brs($passage,$seperator=''){
        //see https://stackoverflow.com/questions/5946114/how-to-replace-newline-or-r-n-with-br
        return str_replace("\r\n",$seperator . '<br>' . $seperator,$passage);
        //this is better but we can not pad the replacement and we need that
        //return nl2br($passage);
    }

    public static function fetch_duration_from_transcript($fulltranscript) {
        //if we do not have the full transcript return 0
        if(!$fulltranscript || empty($fulltranscript)){
            return 0;
        }

        $transcript =  json_decode($fulltranscript);
        if(isset($transcript->results)){
            $duration = self::fetch_duration_from_transcript_json($fulltranscript);
        }else{
            $duration = self::fetch_duration_from_transcript_gjson($fulltranscript);
        }
        return $duration;

    }

    public static function fetch_duration_from_transcript_json($fulltranscript){

        //if we do not have the full transcript return 0
        if(!$fulltranscript || empty($fulltranscript)){
            return 0;
        }

        $transcript = json_decode($fulltranscript);
        $titems=$transcript->results->items;
        $twords=array();
        foreach($titems as $titem){
            if($titem->type == 'pronunciation'){
                $twords[] = $titem;
            }
        }
        $lastindex = count($twords);
        if($lastindex>0){
            return round($twords[$lastindex-1]->end_time,0);
        }else{
            return 0;
        }
    }

    public static function fetch_duration_from_transcript_gjson($fulltranscript){
        //if we do not have the full transcript return 0
        if(!$fulltranscript || empty($fulltranscript)){
            return 0;
        }

        $transcript =  json_decode($fulltranscript);
        $twords=[];
        //create a big array of 'words' from gjson sentences
        foreach($transcript as $sentence) {
            $twords = array_merge($twords,$sentence->words);

        }//end of sentence
        $twordcount=count($twords);
        if($twordcount>0){
            $tword = $twords[$twordcount-1];
            $ms =round(floatval($tword->endTime->nanos * .000000001),2);
            return round($tword->endTime->seconds + $ms,0);
        }else{
            return 0;
        }
    }

    public static function fetch_audio_points($fulltranscript,$matches,$alternatives) {

        //first check if we have a fulltranscript (we might only have a transcript in some cases)
        //if not we just return dummy audio points. Que sera sera
        if (!self::is_json($fulltranscript)) {
            foreach ($matches as $matchitem) {
                $matchitem->audiostart = 0;
                $matchitem->audioend = 0;
            }
            return $matches;
        }
        $transcript =  json_decode($fulltranscript);
        if(isset($transcript->results)){
            $matches = self::fetch_audio_points_json($transcript,$matches,$alternatives);
        }else{
            $matches = self::fetch_audio_points_gjson($transcript,$matches,$alternatives);
        }
        return $matches;
    }


    //fetch start-time and end-time points for each word
    public static function fetch_audio_points_json($transcript,$matches,$alternatives){

        //get type 'pronunciation' items from full transcript. The other type is 'punctuation'.
        $titems=$transcript->results->items;
        $twords=array();
        foreach($titems as $titem){
            if($titem->type == 'pronunciation'){
                $twords[] = $titem;
            }
        }
        $twordcount=count($twords);

        //loop through matches and fetch audio start from word item
        foreach ($matches as $matchitem){
            if($matchitem->tposition <= $twordcount){
                //pull the word data object from the full transcript, at the index of the match
                $tword = $twords[$matchitem->tposition - 1];

                //trust or be sure by matching ...
                $trust = false;
                if($trust){
                    $matchitem->audiostart = $tword->start_time;
                    $matchitem->audioend = $tword->end_time;
                }else {
                    //format the text of the word to lower case no punc, to match the word in the matchitem
                    $tword_text = strtolower($tword->alternatives[0]->content);
                    $tword_text = preg_replace("#[[:punct:]]#", "", $tword_text);
                    //if we got it, fetch the audio position from the word data object
                    if ($matchitem->word == $tword_text) {
                        $matchitem->audiostart = $tword->start_time;
                        $matchitem->audioend = $tword->end_time;

                        //do alternatives search for match
                    }elseif(diff::check_alternatives_for_match($matchitem->word,
                            $tword_text,
                            $alternatives)){
                        $matchitem->audiostart = $tword->start_time;
                        $matchitem->audioend = $tword->end_time;
                    }
                }
            }
        }
        return $matches;
    }

    //fetch start-time and end-time points for each word
    public static function fetch_audio_points_gjson($transcript,$matches,$alternatives){
        $twords=[];
        //create a big array of 'words' from gjson sentences
        foreach($transcript as $sentence) {
            $twords = array_merge($twords,$sentence->words);

        }//end of sentence
        $twordcount=count($twords);

        //loop through matches and fetch audio start from word item
        foreach ($matches as $matchitem) {
            if ($matchitem->tposition <= $twordcount) {
                //pull the word data object from the full transcript, at the index of the match
                $tword = $twords[$matchitem->tposition - 1];
                //make startTime and endTime match the regular format
                $start_time = $tword->startTime->seconds + round(floatval($tword->startTime->nanos * .000000001),2);
                $end_time = $tword->endTime->seconds + round(floatval($tword->endTime->nanos * .000000001),2);

                //trust or be sure by matching ...
                $trust = false;
                if ($trust) {
                    $matchitem->audiostart = $start_time;
                    $matchitem->audioend = $end_time;
                } else {
                    //format the text of the word to lower case no punc, to match the word in the matchitem
                    $tword_text = strtolower($tword->word);
                    $tword_text = preg_replace("#[[:punct:]]#", "", $tword_text);
                    //if we got it, fetch the audio position from the word data object
                    if ($matchitem->word == $tword_text) {
                        $matchitem->audiostart = $start_time;
                        $matchitem->audioend = $end_time;

                        //do alternatives search for match
                    } else if (diff::check_alternatives_for_match($matchitem->word,
                            $tword_text,
                            $alternatives)) {
                        $matchitem->audiostart = $start_time;
                        $matchitem->audioend = $end_time;
                    }
                }
            }
        }//end of words

        return $matches;
    }


    //this is a server side implementation of the same name function in gradenowhelper.js
    //we need this when calculating adjusted grades(reports/machinegrading.php) and on making machine grades(aigrade.php)
    public static function processscores($sessiontime,$sessionendword,$errorcount,$activitydata){

        ////wpm score
        $wpmerrors = $errorcount;
        if($sessiontime > 0) {
            $wpmscore = round(($sessionendword - $wpmerrors) * 60 / $sessiontime);
        }else{
            $wpmscore =0;
        }

        //accuracy score
        if($sessionendword > 0) {
            $accuracyscore = round(($sessionendword - $errorcount) / $sessionendword * 100);
        }else{
            $accuracyscore=0;
        }

        //sessionscore
        $usewpmscore = $wpmscore;
        $targetwpm = $activitydata->targetwpm;
        if($usewpmscore > $targetwpm){
            $usewpmscore = $targetwpm;
        }
        $sessionscore = round($usewpmscore/$targetwpm * 100);

        $scores= new \stdClass();
        $scores->wpmscore = $wpmscore;
        $scores->accuracyscore = $accuracyscore;
        $scores->sessionscore=$sessionscore;
        return $scores;

    }

    //take a json string of session errors/self-corrections, and count how many there are.
    public static function count_objects($items){
        $objects = json_decode($items);
        if($objects){
            $thecount = count(get_object_vars($objects));
        }else{
            $thecount=0;
        }
        return $thecount;
    }

    //get all the aievaluations for a user
    public static function get_aieval_byuser($poodlltimeid,$userid){
        global $DB;
        $sql = "SELECT tai.*  FROM {" . constants::M_AITABLE . "} tai INNER JOIN  {" . constants::M_USERTABLE . "}" .
            " tu ON tu.id =tai.attemptid AND tu.poodlltimeid=tai.poodlltimeid WHERE tu.poodlltimeid=? AND tu.userid=?";
        $result = $DB->get_records_sql($sql,array($poodlltimeid,$userid));
        return $result;
    }

    //get average difference between human graded attempt error count and AI error count
    //we only fetch if A) have machine grade and B) sessiontime> 0(has been manually graded)
    public static function estimate_errors($poodlltimeid){
        global $DB;
        $errorestimate =0;
        $sql = "SELECT AVG(tai.errorcount - tu.errorcount) as errorestimate  FROM {" . constants::M_AITABLE . "} tai INNER JOIN  {" . constants::M_USERTABLE . "}" .
            " tu ON tu.id =tai.attemptid AND tu.poodlltimeid=tai.poodlltimeid WHERE tu.sessiontime > 0 AND tu.poodlltimeid=?";
        $result = $DB->get_field_sql($sql,array($poodlltimeid));
        if($result!==false){
            $errorestimate = round($result);
        }
        return $errorestimate;
    }

    /*
* Per passageword, an object with mistranscriptions and their frequency will be returned
  * To be consistent with how data is stored in matches/errors, we return a 1 based array of mistranscriptions
   * @return array an array of stdClass (1 item per passage word) with the passage index(1 based), passage word and array of mistranscription=>count
 */
    public static function fetch_all_mistranscriptions($poodlltimeid)
    {
        global $DB;
        $attempts = $DB->get_records(constants::M_AITABLE ,array('poodlltimeid'=>$poodlltimeid));
        $activity = $DB->get_record(constants::M_TABLE,array('id'=>$poodlltimeid));
        $passagewords = diff::fetchWordArray($activity->passage);
        $passagecount = count($passagewords);
        //$alternatives = diff::fetchAlternativesArray($activity->alternatives);

        $results= array();
        $mistranscriptions= array();
        foreach($attempts as $attempt){
            $transcriptwords = diff::fetchWordArray($attempt->transcript);
            $matches = json_decode($attempt->sessionmatches);
            $mistranscriptions[]= self::fetch_attempt_mistranscriptions($passagewords,$transcriptwords,$matches);
        }
        //aggregate results
        for($wordnumber=1;$wordnumber<=$passagecount;$wordnumber++){
            $aggregate_set = array();
            foreach($mistranscriptions as $mistranscript){
                if(!$mistranscript[$wordnumber]){continue;}
                if(array_key_exists($mistranscript[$wordnumber],$aggregate_set)){
                    $aggregate_set[$mistranscript[$wordnumber]]++;
                }else{
                    $aggregate_set[$mistranscript[$wordnumber]]=1;
                }
            }
            $result= new \stdClass();
            $result->mistranscriptions=$aggregate_set;
            $result->passageindex=$wordnumber;
            $result->passageword=$passagewords[$wordnumber-1];
            $results[] = $result;
        }//end of for loop
        return $results;
    }


    /*
   * This will return an array of mistranscript strings for a single attemot. 1 entry per passageword.
     * To be consistent with how data is stored in matches/errors, we return a 1 based array of mistranscriptions
     * @return array a 1 based array of mistranscriptions(string) or false. i item for each passage word
    */
    public static function fetch_attempt_mistranscriptions($passagewords,$transcriptwords,$matches)
    {
        $passagecount = count($passagewords);
        if(!$passagecount){return false;}
        $mistranscriptions=array();
        for($wordnumber=1;$wordnumber<=$passagecount;$wordnumber++){
            $mistranscription = self::fetch_one_mistranscription($wordnumber,$transcriptwords,$matches);
            if($mistranscription){
                $mistranscriptions[$wordnumber]=$mistranscription;
            }else{
                $mistranscriptions[$wordnumber]=false;
            }
        }//end of for loop
        return $mistranscriptions;
    }

    /*
   * This will take a wordindex and find the previous and next transcript indexes that were matched and
   * return all the transcript words in between those.
     *
     * @return a string which is the transcript match of a passage word, or false if the transcript=passage
    */
    public static function fetch_one_mistranscription($passageindex,$transcriptwords,$matches){

        //count transcript words
        $transcriptlength= count($transcriptwords);
        if($transcriptlength==0){
            return false;
        }

        //build a quick to search array of matched words
        $passagematches=array();
        foreach($matches as $match){
            $passagematches[$match->pposition]=$match->word;
        }

        //find startindex
        $startindex=-1;
        for($wordnumber=$passageindex;$wordnumber>0;$wordnumber--){

            $ismatched =array_key_exists($wordnumber,$passagematches);
            if($ismatched){
                $startindex=$matches->{$wordnumber}->tposition+1;
                break;
            }
        }//end of for loop

        //find endindex
        $endindex=-1;
        for($wordnumber=$passageindex;$wordnumber<=$transcriptlength;$wordnumber++){

            $ismatched =array_key_exists($wordnumber,$passagematches);
            //if we matched then the previous transcript word is the last unmatched one in the checkindex sequence
            if($ismatched){
                $endindex=$matches->{$wordnumber}->tposition-1;
                break;
            }
        }//end of for loop --

        //if there was no previous matched word, we set start to 1
        if($startindex==-1){$startindex=1;}
        //if there was no subsequent matched word we flag the end as the -1
        if($endindex==$transcriptlength){
            $endindex=-1;
            //an edge case is where the first word is not in transcript and first match is the second or later passage
            //word. It might not be possible for endindex to be lower than start index, but we don't want it anyway
        }else if($endindex==0 || $endindex < $startindex){
            return false;
        }

        //up until this point the indexes have started from 1, since the passage word numbers start from 1
        //but the transcript array is 0 based so we adjust. array_slice function does not include item and endindex
        ///so it needs to be one more then start index. hence we do not adjust that
        $startindex--;

        //finally we return the section of transcript
        if($endindex>0) {
            $chunklength = $endindex-$startindex;
            $retarray = array_slice($transcriptwords,$startindex, $chunklength);
        }else{
            $retarray = array_slice($transcriptwords,$startindex);
        }

        $ret = implode(" ",$retarray);
        if(trim($ret)==''){
            return false;
        }else{
            return $ret;
        }
    }


    /**
     * Returns the link for the related activity
     * @return string
     */
    public static function fetch_next_activity($activitylink) {
        global $DB;
        $ret = new \stdClass();
        $ret->url=false;
        $ret->label=false;
        if(!$activitylink){
            return $ret;
        }

        $module = $DB->get_record('course_modules', array('id' => $activitylink));
        if ($module) {
            $modname = $DB->get_field('modules', 'name', array('id' => $module->module));
            if ($modname) {
                $instancename = $DB->get_field($modname, 'name', array('id' => $module->instance));
                if ($instancename) {
                    $ret->url = new \moodle_url('/mod/'.$modname.'/view.php', array('id' => $activitylink));
                    $ret->label = get_string('activitylinkname',constants::M_COMPONENT, $instancename);
                }
            }
        }
        return $ret;
    }

    //What to show students after an attempt
    public static function get_postattempt_options(){
        return array(
            constants::POSTATTEMPT_NONE => get_string("postattempt_none",constants::M_COMPONENT),
            constants::POSTATTEMPT_EVAL  => get_string("postattempt_eval",constants::M_COMPONENT),
            constants::POSTATTEMPT_EVALERRORS  => get_string("postattempt_evalerrors",constants::M_COMPONENT)
        );
    }

  public static function get_region_options(){
      return array(
        "useast1" => get_string("useast1",constants::M_COMPONENT),
          "tokyo" => get_string("tokyo",constants::M_COMPONENT),
          "sydney" => get_string("sydney",constants::M_COMPONENT),
          "dublin" => get_string("dublin",constants::M_COMPONENT),
          "ottawa" => get_string("ottawa",constants::M_COMPONENT),
          "frankfurt" => get_string("frankfurt",constants::M_COMPONENT),
          "london" => get_string("london",constants::M_COMPONENT),
          "saopaulo" => get_string("saopaulo",constants::M_COMPONENT),
          "singapore" => get_string("singapore",constants::M_COMPONENT),
          "mumbai" => get_string("mumbai",constants::M_COMPONENT)
      );
  }

    public static function get_machinegrade_options(){
        return array(
            constants::MACHINEGRADE_NONE => get_string("machinegradenone",constants::M_COMPONENT),
            constants::MACHINEGRADE_MACHINE => get_string("machinegrademachine",constants::M_COMPONENT)
        );
    }

    public static function get_timelimit_options(){
        return array(
            0 => get_string("notimelimit",constants::M_COMPONENT),
            30 => get_string("xsecs",constants::M_COMPONENT,'30'),
            45 => get_string("xsecs",constants::M_COMPONENT,'45'),
            60 => get_string("onemin",constants::M_COMPONENT),
            90 => get_string("oneminxsecs",constants::M_COMPONENT,'30'),
            120 => get_string("xmins",constants::M_COMPONENT,'2'),
            150 => get_string("xminsecs",constants::M_COMPONENT,array('minutes'=>2,'seconds'=>30)),
            180 => get_string("xmins",constants::M_COMPONENT,'3')
        );
    }

  public static function get_expiredays_options(){
      return array(
          "1"=>"1",
          "3"=>"3",
          "7"=>"7",
          "30"=>"30",
          "90"=>"90",
          "180"=>"180",
          "365"=>"365",
          "730"=>"730",
          "9999"=>get_string('forever',constants::M_COMPONENT)
      );
  }

    //convert a phrase or word to a series of phonetic characters that we can use to compare text/spoken
    public static function convert_to_phonetic($phrase,$language){

        switch($language){
            case 'en':
                $phonetic = metaphone($phrase);
                break;
            case 'ja':
            default:
                $phonetic = $phrase;
        }
        return $phonetic;
    }

    public static function fetch_options_transcribers() {
        $options = array(constants::TRANSCRIBER_AMAZONTRANSCRIBE => get_string("transcriber_amazontranscribe", constants::M_COMPONENT),
                constants::TRANSCRIBER_GOOGLECLOUDSPEECH => get_string("transcriber_googlecloud", constants::M_COMPONENT));
        return $options;
    }

    public static function fetch_pagelayout_options(){
        $options = Array(
                'frametop'=>'frametop',
                'embedded'=>'embedded',
                'mydashboard'=>'mydashboard',
                'incourse'=>'incourse',
                'standard'=>'standard',
                'popup'=>'popup'
        );
        return $options;
    }


    public static function get_lang_options(){
       return array(
               constants::M_LANG_ARAE => get_string('ar-ae', constants::M_COMPONENT),
               constants::M_LANG_ARSA => get_string('ar-sa', constants::M_COMPONENT),
               constants::M_LANG_DADK => get_string('da-dk', constants::M_COMPONENT),
               constants::M_LANG_DEDE => get_string('de-de', constants::M_COMPONENT),
               constants::M_LANG_DECH => get_string('de-ch', constants::M_COMPONENT),
               constants::M_LANG_ENUS => get_string('en-us', constants::M_COMPONENT),
               constants::M_LANG_ENGB => get_string('en-gb', constants::M_COMPONENT),
               constants::M_LANG_ENAU => get_string('en-au', constants::M_COMPONENT),
               constants::M_LANG_ENIN => get_string('en-in', constants::M_COMPONENT),
               constants::M_LANG_ENIE => get_string('en-ie', constants::M_COMPONENT),
               constants::M_LANG_ENWL => get_string('en-wl', constants::M_COMPONENT),
               constants::M_LANG_ENAB => get_string('en-ab', constants::M_COMPONENT),
               constants::M_LANG_ESUS => get_string('es-us', constants::M_COMPONENT),
               constants::M_LANG_ESES => get_string('es-es', constants::M_COMPONENT),
               constants::M_LANG_FAIR => get_string('fa-ir', constants::M_COMPONENT),
               constants::M_LANG_FRCA => get_string('fr-ca', constants::M_COMPONENT),
               constants::M_LANG_FRFR => get_string('fr-fr', constants::M_COMPONENT),
               constants::M_LANG_HIIN => get_string('hi-in', constants::M_COMPONENT),
               constants::M_LANG_HEIL => get_string('he-il', constants::M_COMPONENT),
               constants::M_LANG_IDID => get_string('id-id', constants::M_COMPONENT),
               constants::M_LANG_ITIT => get_string('it-it', constants::M_COMPONENT),
               constants::M_LANG_JAJP => get_string('ja-jp', constants::M_COMPONENT),
               constants::M_LANG_KOKR => get_string('ko-kr', constants::M_COMPONENT),
               constants::M_LANG_MSMY => get_string('ms-my', constants::M_COMPONENT),
               constants::M_LANG_NLNL => get_string('nl-nl', constants::M_COMPONENT),
               constants::M_LANG_PTBR => get_string('pt-br', constants::M_COMPONENT),
               constants::M_LANG_PTPT => get_string('pt-pt', constants::M_COMPONENT),
               constants::M_LANG_RURU => get_string('ru-ru', constants::M_COMPONENT),
               constants::M_LANG_TAIN => get_string('ta-in', constants::M_COMPONENT),
               constants::M_LANG_TEIN => get_string('te-in', constants::M_COMPONENT),
               constants::M_LANG_TRTR => get_string('tr-tr', constants::M_COMPONENT),
               constants::M_LANG_ZHCN => get_string('zh-cn', constants::M_COMPONENT)
       );
	/*
      return array(
			"none"=>"No TTS",
			"af"=>"Afrikaans", 
			"sq"=>"Albanian", 
			"am"=>"Amharic", 
			"ar"=>"Arabic", 
			"hy"=>"Armenian", 
			"az"=>"Azerbaijani", 
			"eu"=>"Basque", 
			"be"=>"Belarusian", 
			"bn"=>"Bengali", 
			"bh"=>"Bihari", 
			"bs"=>"Bosnian", 
			"br"=>"Breton", 
			"bg"=>"Bulgarian", 
			"km"=>"Cambodian", 
			"ca"=>"Catalan", 
			"zh-CN"=>"Chinese (Simplified)", 
			"zh-TW"=>"Chinese (Traditional)", 
			"co"=>"Corsican", 
			"hr"=>"Croatian", 
			"cs"=>"Czech", 
			"da"=>"Danish", 
			"nl"=>"Dutch", 
			"en"=>"English", 
			"eo"=>"Esperanto", 
			"et"=>"Estonian", 
			"fo"=>"Faroese", 
			"tl"=>"Filipino", 
			"fi"=>"Finnish", 
			"fr"=>"French", 
			"fy"=>"Frisian", 
			"gl"=>"Galician", 
			"ka"=>"Georgian", 
			"de"=>"German", 
			"el"=>"Greek", 
			"gn"=>"Guarani", 
			"gu"=>"Gujarati", 
			"xx-hacker"=>"Hacker", 
			"ha"=>"Hausa", 
			"iw"=>"Hebrew", 
			"hi"=>"Hindi", 
			"hu"=>"Hungarian", 
			"is"=>"Icelandic", 
			"id"=>"Indonesian", 
			"ia"=>"Interlingua", 
			"ga"=>"Irish", 
			"it"=>"Italian", 
			"ja"=>"Japanese", 
			"jw"=>"Javanese", 
			"kn"=>"Kannada", 
			"kk"=>"Kazakh", 
			"rw"=>"Kinyarwanda", 
			"rn"=>"Kirundi", 
			"xx-klingon"=>"Klingon", 
			"ko"=>"Korean", 
			"ku"=>"Kurdish", 
			"ky"=>"Kyrgyz", 
			"lo"=>"Laothian", 
			"la"=>"Latin", 
			"lv"=>"Latvian", 
			"ln"=>"Lingala", 
			"lt"=>"Lithuanian", 
			"mk"=>"Macedonian", 
			"mg"=>"Malagasy", 
			"ms"=>"Malay", 
			"ml"=>"Malayalam", 
			"mt"=>"Maltese", 
			"mi"=>"Maori", 
			"mr"=>"Marathi", 
			"mo"=>"Moldavian", 
			"mn"=>"Mongolian", 
			"sr-ME"=>"Montenegrin", 
			"ne"=>"Nepali", 
			"no"=>"Norwegian", 
			"nn"=>"Norwegian(Nynorsk)", 
			"oc"=>"Occitan", 
			"or"=>"Oriya", 
			"om"=>"Oromo", 
			"ps"=>"Pashto", 
			"fa"=>"Persian", 
			"xx-pirate"=>"Pirate", 
			"pl"=>"Polish", 
			"pt-BR"=>"Portuguese(Brazil)", 
			"pt-PT"=>"Portuguese(Portugal)", 
			"pa"=>"Punjabi", 
			"qu"=>"Quechua", 
			"ro"=>"Romanian", 
			"rm"=>"Romansh", 
			"ru"=>"Russian", 
			"gd"=>"Scots Gaelic", 
			"sr"=>"Serbian", 
			"sh"=>"Serbo-Croatian", 
			"st"=>"Sesotho", 
			"sn"=>"Shona", 
			"sd"=>"Sindhi", 
			"si"=>"Sinhalese", 
			"sk"=>"Slovak", 
			"sl"=>"Slovenian", 
			"so"=>"Somali", 
			"es"=>"Spanish", 
			"su"=>"Sundanese", 
			"sw"=>"Swahili", 
			"sv"=>"Swedish", 
			"tg"=>"Tajik", 
			"ta"=>"Tamil", 
			"tt"=>"Tatar", 
			"te"=>"Telugu", 
			"th"=>"Thai", 
			"ti"=>"Tigrinya", 
			"to"=>"Tonga", 
			"tr"=>"Turkish", 
			"tk"=>"Turkmen", 
			"tw"=>"Twi", 
			"ug"=>"Uighur", 
			"uk"=>"Ukrainian", 
			"ur"=>"Urdu", 
			"uz"=>"Uzbek", 
			"vi"=>"Vietnamese", 
			"cy"=>"Welsh", 
			"xh"=>"Xhosa", 
			"yi"=>"Yiddish", 
			"yo"=>"Yoruba", 
			"zu"=>"Zulu"
		);
	*/
   }
}
