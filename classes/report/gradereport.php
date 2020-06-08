<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 20:52
 */

namespace mod_poodlltime\report;

use \mod_poodlltime\constants;
use \mod_poodlltime\utils;

class gradereport extends basereport
{

    protected $report="gradereport";
    protected $fields = array('id','username','audiofile','wpm','accuracy_p','quiz_p','grader','timecreated','deletenow');
    protected $headingdata = null;
    protected $qcache=array();
    protected $ucache=array();


    public function fetch_formatted_field($field,$record,$withlinks)
    {
        global $DB, $CFG, $OUTPUT;
        switch ($field) {
            case 'id':
                $ret = $record->id;
                break;

            case 'username':
                $user = $this->fetch_cache('user', $record->userid);
                $ret = fullname($user);
                break;

            case 'audiofile':
                if ($withlinks) {
                    /*
                    $ret = html_writer::tag('audio','',
                            array('controls'=>'','src'=>$record->audiourl));
                        */
                    $ret = \html_writer::div('<i class="fa fa-play-circle"></i>',
                        constants::M_HIDDEN_PLAYER_BUTTON, array('data-audiosource' => $record->audiourl));

                } else {
                    $ret = get_string('submitted', constants::M_COMPONENT);
                }
                break;
                break;

            case 'wpm':
                $ret = $record->wpm;
                break;


            case 'accuracy_p':
                $ret = $record->accuracy;
                break;

            case 'grade_p':
                $ret = $record->sessionscore;
                break;

            case 'quiz_p':
                $ret = $record->qscore;
                break;

            case 'grader':
                if($record->sessiontime ==0 && $record->wpm){
                    $ret = get_string('grader_ai',constants::M_COMPONENT);
                }else if($record->sessiontime){
                    $ret = get_string('grader_human',constants::M_COMPONENT);
                }else{
                    $ret =get_string('grader_ungraded',constants::M_COMPONENT);
                }
                break;

            case 'timecreated':
                $ret = date("Y-m-d H:i:s", $record->timecreated);
                break;

            case 'deletenow':
                if ($withlinks) {
                    $url = new \moodle_url(constants::M_URL . '/manageattempts.php',
                        array('action' => 'delete', 'n' => $record->poodlltimeid, 'attemptid' => $record->id, 'source' => $this->report));
                    $btn = new \single_button($url, get_string('delete'), 'post');
                    $btn->add_confirm_action(get_string('deleteattemptconfirm', constants::M_COMPONENT));
                    $ret = $OUTPUT->render($btn);
                }else {
                    $ret = '';
                }
                break;

            default:
                if (property_exists($record, $field)) {
                    $ret = $record->{$field};
                } else {
                    $ret = '';
                }
        }
        return $ret;
    }

    public function fetch_formatted_heading(){
        $record = $this->headingdata;
        $ret='';
        if(!$record){return $ret;}
        //$ec = $this->fetch_cache(constants::M_TABLE,$record->englishcentralid);
        return get_string('gradereportheading',constants::M_COMPONENT);

    }

    public function process_raw_data($formdata){
        global $DB;

        //heading data
        $this->headingdata = new \stdClass();

        $emptydata = array();
        $user_attempt_totals = array();

        //if we are not machine grading the SQL is simpler
        $human_sql = "SELECT tu.*  FROM {" . constants::M_USERTABLE . "} tu INNER JOIN {user} u ON tu.userid=u.id WHERE tu.poodlltimeid=?" .
            " ORDER BY u.lastnamephonetic,u.firstnamephonetic,u.lastname,u.firstname,u.middlename,u.alternatename,tu.id DESC";

        //if we are machine grading we need to fetch human and machine so we can get WPM etc from either
        $hybrid_sql="SELECT tu.*,tai.accuracy as aiaccuracy,tai.wpm as aiwpm, tai.sessionscore as aisessionscore  FROM {" . constants::M_USERTABLE . "} tu INNER JOIN {user} u ON tu.userid=u.id " .
            "INNER JOIN {". constants::M_AITABLE ."} tai ON tai.attemptid=tu.id " .
            "WHERE tu.poodlltimeid=?" .
            " ORDER BY u.lastnamephonetic,u.firstnamephonetic,u.lastname,u.firstname,u.middlename,u.alternatename,tu.id DESC";

        //we need a module instance to know which scoring method we are using.
        $moduleinstance = $DB->get_record(constants::M_TABLE,array('id'=>$formdata->poodlltimeid));
        $cantranscribe = utils::can_transcribe($moduleinstance);

        //run the sql and match up WPM/ accuracy and sessionscore if we need to
        if($moduleinstance->machgrademethod==constants::MACHINEGRADE_MACHINE && $cantranscribe) {
            $alldata = $DB->get_records_sql($hybrid_sql, array($formdata->poodlltimeid));
            if ($alldata) {
                //sessiontime is our indicator that a human grade has been saved.
                foreach ($alldata as $result) {
                    if (!$result->sessiontime) {
                        $result->wpm = $result->aiwpm;
                        $result->accuracy = $result->aiaccuracy;
                        $result->sessionscore = $result->aisessionscore;
                    }
                }
            }
        }else{
            $alldata =$DB->get_records_sql($human_sql, array($formdata->poodlltimeid));
        }


        //loop through data getting most recent attempt
        if ($alldata) {

            foreach ($alldata as $thedata) {

                //we ony take the most recent attempt
                if (array_key_exists($thedata->userid, $user_attempt_totals)) {
                    $user_attempt_totals[$thedata->userid] = $user_attempt_totals[$thedata->userid] + 1;
                    continue;
                }
                $user_attempt_totals[$thedata->userid] = 1;

                $thedata->audiourl = \mod_poodlltime\utils::make_audio_URL($thedata->filename, $formdata->modulecontextid, constants::M_COMPONENT,
                    constants::M_FILEAREA_SUBMISSIONS, $thedata->id);
                $this->rawdata[] = $thedata;
            }
            foreach ($this->rawdata as $thedata) {
                $thedata->totalattempts = $user_attempt_totals[$thedata->userid];
            }
        } else {
            $this->rawdata = $emptydata;
        }
        return true;
    }

}