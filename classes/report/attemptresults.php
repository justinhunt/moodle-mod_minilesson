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

class attemptresults extends basereport
{

    protected $report="attemptresults";
    protected $fields = array('qnumber','title','result','grade_p');
    protected $headingdata = null;
    protected $qcache=array();
    protected $ucache=array();



    public function fetch_formatted_heading(){
        $record = $this->headingdata;
        if(!$record){return '';}
        $user = $this->fetch_cache('user',$record->userid);
        $a = new \stdClass();
        $a->username = fullname($user);
        $a->date = date("Y-m-d H:i:s", $record->timecreated);
        $a->attemptid = $record->id;
        $a->sessionscore = $record->sessionscore;
        return get_string('attemptresultsheading',constants::M_COMPONENT,$a);

    }

    public function fetch_formatted_field($field, $record, $withlinks)
    {
        global $DB, $CFG, $OUTPUT;


        switch ($field) {
            case 'qnumber':
                $ret = $record->index;
                break;

            case 'title':
                $ret = $record->title;
                break;

            case 'result':
                $ret = $record->correctitems . '/' . $record->totalitems;
                break;

            //grade could hold either human or ai data
            case 'grade_p':
                //if not human or ai graded
                $ret = $record->grade;
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

        $this->rawdata = [];

        //get the comp test quiz data
        $moduleinstance = $DB->get_record(constants::M_TABLE, array('id' => $formdata->moduleid), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id, $course->id, false, MUST_EXIST);
        $comp_test =  new \mod_poodlltime\comprehensiontest($cm);
        $quizdata = $comp_test->fetch_test_data_for_js();

        $emptydata = array();


        //we jsut need the  individual recoen
        $record =$DB->get_record(constants::M_ATTEMPTSTABLE,
                array('id'=>$formdata->attemptid,'poodlltimeid'=>$formdata->moduleid));


        if ($record) {
                //heading data
                $this->headingdata= $record;

                $steps = json_decode($record->sessiondata)->steps;
                $results = array_filter($steps, function($step){return $step->hasgrade;});
                foreach($results as $result){
                    $result->title=$quizdata[$result->index]->title;
                    $result->index++;
                }
                $this->rawdata = $results;

        } else {
            //heading data
            $this->headingdata= false;
            $this->rawdata = $emptydata;
        }
        return true;
    }//end of function

}