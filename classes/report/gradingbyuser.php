<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 20:52
 */

namespace mod_minilesson\report;

use \mod_minilesson\constants;
use \mod_minilesson\utils;

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


        switch ($field) {
            case 'id':
                $ret = $record->id;
                break;


            //grade could hold either human or ai data
            case 'grade_p':
                $ret = $record->sessionscore;
                if ($withlinks) {
                    $link = new \moodle_url(constants::M_URL . '/reports.php',
                            array('report' => 'attemptresults', 'n' => $record->moduleid, 'attemptid' => $record->id));
                    $ret = \html_writer::link($link, $ret);
                }
                break;


            case 'timecreated':
                $ret = date("Y-m-d H:i:s", $record->timecreated);
                break;

            case 'deletenow':
                if ($withlinks) {
                    $url = new \moodle_url(constants::M_URL . '/manageattempts.php',
                        array('action' => 'delete', 'n' => $record->moduleid, 'attemptid' => $record->id, 'source' => $this->report));
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
        $human_sql = "SELECT tu.* FROM {" . constants::M_ATTEMPTSTABLE . "} tu " .
            " WHERE tu.moduleid=? AND tu.status=" . constants::M_STATE_COMPLETE .
            " AND tu.userid=? " .
            " ORDER BY tu.id DESC";

        $alldata =$DB->get_records_sql($human_sql, array($formdata->moduleid, $formdata->userid));


        if ($alldata) {
            foreach ($alldata as $thedata) {
                $this->rawdata[] = $thedata;
            }

        } else {
            $this->rawdata = $emptydata;
        }
        return true;
    }//end of function

}