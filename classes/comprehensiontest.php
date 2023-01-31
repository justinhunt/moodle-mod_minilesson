<?php
/**
 * Created by PhpStorm.
 * User: justin
 * Date: 17/08/29
 * Time: 16:12
 */

namespace mod_minilesson;


class comprehensiontest
{
    protected $cm;
    protected $context;
    protected $mod;
    protected $items;

    public function __construct($cm) {
        global $DB;
        $this->cm = $cm;
        $this->mod = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $this->context = \context_module::instance($cm->id);
        $this->course =$DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    }

    public function fetch_item_count()
    {
        global $DB;
        if (!$this->items) {
            $this->items = $DB->get_records(constants::M_QTABLE, ['minilesson' => $this->mod->id],'itemorder ASC');
        }
        if($this->items){
            return count($this->items);
        }else{
            return 0;
        }
    }

    public function fetch_items()
    {
        global $DB;
        if (!$this->items) {
            $this->items = $DB->get_records(constants::M_QTABLE, ['minilesson' => $this->mod->id],'itemorder ASC');
        }
        if($this->items){
            return $this->items;
        }else{
            return [];
        }
    }

    public function fetch_latest_attempt($userid){
        global $DB;

        $attempts = $DB->get_records(constants::M_ATTEMPTSTABLE,array('moduleid' => $this->mod->id,'userid'=>$userid),'id DESC');
        if($attempts){
            $attempt = array_shift($attempts);
            return $attempt;
        }else{
            return false;
        }
    }

    /* return the test items suitable for js to use */
    public function fetch_test_data_for_js($renderer=false){
        global $CFG, $USER, $OUTPUT;

        $items = $this->fetch_items();

        //first confirm we are authorised before we try to get the token
        $config = get_config(constants::M_COMPONENT);
        if(empty($config->apiuser) || empty($config->apisecret)){
            $errormessage = get_string('nocredentials',constants::M_COMPONENT,
                    $CFG->wwwroot . constants::M_PLUGINSETTINGS);
            //return error?
            $token=false;
        }else {
            //fetch token
            $token = utils::fetch_token($config->apiuser,$config->apisecret);

            //check token authenticated and no errors in it
            $errormessage = utils::fetch_token_error($token);
            if(!empty($errormessage)){
                //return error?
                //return $this->show_problembox($errormessage);
            }
        }


        //prepare data array for test
        $testitems=array();
        $currentitem=0;
        foreach($items as $item) {
            $currentitem++;
            $t_item=utils::fetch_item_from_itemrecord($item,$this->mod,$this->context);
            $t_item->set_token($token);
            $t_item->set_currentnumber($currentitem);
            //add our item to test
            if(!$renderer){$renderer=$OUTPUT;}
            $testitems[]=$t_item->export_for_template($renderer);
        }//end of loop
        return $testitems;
    }

    /* called from ajaxhelper to grade test */
    public function grade_test($answers){

        $items = $this->fetch_items();
        $currentitem=0;
        $score=0;
        foreach($items as $item) {
            $currentitem++;
            if (isset($answers->{'' . $currentitem})) {
                if ($item->correctanswer == $answers->{'' . $currentitem}) {
                    $score++;
                }
            }
        }
        if($score==0 || count($items)==0){
            return 0;
        }else{
            return floor(100 * $score/count($items));
        }
    }


}//end of class