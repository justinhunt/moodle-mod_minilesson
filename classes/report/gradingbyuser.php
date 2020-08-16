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

class gradingbyuser extends basereport
{

    protected $report="gradingbyuser";
    protected $fields = array('id','grade_p','timecreated','deletenow');
    protected $headingdata = null;
    protected $qcache=array();
    protected $ucache=array();



    public function fetch_formatted_heading(){
        $record = $this->headingdata;
        $ret='';
        if(!$record){return $ret;}
        $user = $this->fetch_cache('user',$record->userid);
        return get_string('gradingbyuserheading',constants::M_COMPONENT,fullname($user));

    }

    public function fetch_formatted_field($field, $record, $withlinks)
    {
        global $DB, $CFG, $OUTPUT;

        $has_ai_grade = $record->fulltranscript;

        switch ($field) {
            case 'id':
                $ret = $record->id;
                break;


            //grade could hold either human or ai data
            case 'grade_p':
                //if not human or ai graded
                $ret = $record->sessionscore;
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
                } else {
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

    } //end of function


    public function process_raw_data($formdata)
    {
        global $DB;

        //heading data
        $this->headingdata = new \stdClass();
        $this->rawdata = [];

        //heading data
        $this->headingdata->userid = $formdata->userid;

        $emptydata = array();

        //if we are not machine grading the SQL is simpler
        $human_sql = "SELECT tu.*, false as fulltranscript   FROM {" . constants::M_ATTEMPTSTABLE . "} tu " .
            "WHERE tu.poodlltimeid=? " .
            "AND tu.userid=? " .
            "ORDER BY tu.id DESC";


        //we need a module instance to know which scoring method we are using.
        $moduleinstance = $DB->get_record(constants::M_TABLE,array('id'=>$formdata->poodlltimeid));
        $cantranscribe = utils::can_transcribe($moduleinstance);
        $alldata =$DB->get_records_sql($human_sql, array($formdata->poodlltimeid, $formdata->userid));


        if ($alldata) {
            foreach ($alldata as $thedata) {
                $thedata->audiourl = \mod_poodlltime\utils::make_audio_URL($thedata->filename, $formdata->modulecontextid, constants::M_COMPONENT,
                    constants::M_FILEAREA_SUBMISSIONS, $thedata->id);
                $this->rawdata[] = $thedata;
            }

        } else {
            $this->rawdata = $emptydata;
        }
        return true;
    }//end of function

}