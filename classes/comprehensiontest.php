<?php
/**
 * Created by PhpStorm.
 * User: justin
 * Date: 17/08/29
 * Time: 16:12
 */

namespace mod_poodlltime;


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
    }

    public function fetch_media_url($filearea,$item){
        //get question audio div (not so easy)
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id,  constants::M_COMPONENT,$filearea,$item->id);
        foreach ($files as $file) {
            $filename = $file->get_filename();
            if($filename=='.'){continue;}
            $filepath = '/';
            $mediaurl = \moodle_url::make_pluginfile_url($this->context->id, constants::M_COMPONENT,
                $filearea, $item->id,
                $filepath, $filename);
            return $mediaurl->__toString();

        }
        //We always take the first file and if we have none, thats not good.
        return "";
       // return "$this->context->id pp $filearea pp $item->id";
    }

    public function fetch_items()
    {
        global $DB;
        if (!$this->items) {
            $this->items = $DB->get_records(constants::M_QTABLE, ['poodlltime' => $this->mod->id],'itemorder ASC');
        }
        if($this->items){
            return $this->items;
        }else{
            return [];
        }
    }

    public function fetch_latest_attempt($userid){
        global $DB;

        $attempts = $DB->get_records(constants::M_USERTABLE,array('poodlltimeid' => $this->mod->id,'userid'=>$userid),'id DESC');
        if($attempts){
            $attempt = array_shift($attempts);
            return $attempt;
        }else{
            return false;
        }
    }

    /*we will probably never need to use this again */
    public function fetch_test_data_for_js_files(){

        $items = $this->fetch_items();

        //prepare data array for test
        $testitems=array();
        $currentitem=0;
        $itemcount=count($items);
        $itemid= $this->cm->instance;
        foreach($items as $item) {
            $currentitem++;
            $testitem= new \stdClass();
            $testitem->number =  $currentitem;
            $testitem->text =  file_rewrite_pluginfile_urls($item->{constants::TEXTQUESTION},
                'pluginfile.php', $this->context->id,constants::M_COMPONENT,
                constants::TEXTQUESTION_FILEAREA, $itemid);

            for($anumber=1;$anumber<=constants::MAXANSWERS;$anumber++) {
                $testitem->{'customtext' . $anumber} = file_rewrite_pluginfile_urls($item->{constants::TEXTANSWER . $anumber},
                    'pluginfile.php', $this->context->id,constants::M_COMPONENT,
                    constants::TEXTANSWER_FILEAREA . $anumber, $itemid);
            }
            $testitem->correctanswer =  $item->correctanswer;
            $testitem->id = $item->id;
            $testitem->type=$item->type;
            $testitems[]=$testitem;
        }
        return $testitems;
    }

    /* return the test items suitable for js to use */
    public function fetch_test_data_for_js(){
        global $CFG, $USER;

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
            $testitem= new \stdClass();
            $testitem->number =  $currentitem;
            $testitem->text =  $item->{constants::TEXTQUESTION};
            for($anumber=1;$anumber<=constants::MAXANSWERS;$anumber++) {
                $testitem->{'customtext' . $anumber} = $item->{constants::TEXTANSWER . $anumber};
            }
            $testitem->correctanswer =  $item->correctanswer;
            $testitem->id = $item->id;
            $testitem->type=$item->type;
            $testitem->uniqueid=$item->type . $testitem->number;

            switch($testitem->type){
                case constants::TYPE_DICTATION:
                case constants::TYPE_DICTATIONCHAT:
                case constants::TYPE_SPEECHCARDS:
                case constants::TYPE_LISTENREPEAT:
                   $sentences = explode(PHP_EOL,$testitem->customtext1);
                   $index=0;
                    $testitem->sentences=[];
                   foreach($sentences as $sentence){
                       if(empty(trim($sentence))){continue;}
                       $s = new \stdClass();
                       $s->index=$index;
                       $s->sentence=trim($sentence);
                       $s->length = strlen($s->sentence);
                       $index++;
                       $testitem->sentences[]=$s;
                   }

                   //cloudpoodll stuff
                   $testitem->region =$config->awsregion;
                   $testitem->cloudpoodlltoken = $token;
                   $testitem->wwwroot=$CFG->wwwroot;
                   $testitem->language=$this->mod->ttslanguage;
                   $testitem->hints='';
                   $testitem->owner=hash('md5',$USER->username);

                   break;
            }

            $testitems[]=$testitem;
        }
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