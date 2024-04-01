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

namespace mod_minilesson\local\itemtype;

use mod_minilesson\constants;
use mod_minilesson\utils;
use templatable;
use renderable;

/**
 * Renderable class for an item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class item implements templatable, renderable {

    /** @var boolean force titles */
    protected $forcetitles;

    /** @var array editor optiosns */
    protected $editoroptions;

    /** @var array filemanager options */
    protected $filemanageroptions;

    /** @var int $id The current number in the lesson/container */
    protected $currentnumber;

    /** @var \stdClass $itemrecord The db record for the item. */
    protected $itemrecord;

    /** @var \stdClass $context The mod context. */
    protected $context;

    /** @var \stdClass $course The mod context. */
    protected $course;


    /** @var string $token The cloudpoodll token. */
    protected $token;

    /** @var string $region The aws region. */
    protected $region;

    /** @var string $language The target language. */
    protected $language;

    /** @var \stdClass $moduleinstance The module. */
    protected $moduleinstance;

    //NEEDS SPEECH REC
    protected $needs_speechrec  =false;

    /**
     * The class constructor.
     *
     */
    public function __construct($itemrecord, $moduleinstance=false, $context=false){
        $this->from_record($itemrecord, $moduleinstance, $context);
    }

    /**
     * The class constructor.
     *
     * @param int $currentnumber The current number in the lesson
     * @param \stdClass $itemrecord The db record for the item.
     */
    /*
    public static function from_id($itemid,$moduleinstance=false, $context=false) {
        global $DB;

        $itemrecord = $DB->get_record(constants::M_QTABLE,['id'=>$itemid],'*', MUST_EXIST);
        $instance=self::from_record($itemrecord,$moduleinstance,$context);
        return $instance;
    }
    */

    /**


     * The class constructor.
     *
     * @param int $currentnumber The current number in the lesson
     * @param \stdClass $itemrecord The db record for the item.
     */
    public function from_record($itemrecord, $moduleinstance=false, $context=false) {
        global $DB;

        $this->itemrecord = $itemrecord;
        if(!$moduleinstance){
            $this->moduleinstance = $DB->get_record(constants::M_TABLE,['id'=>$this->itemrecord->minilesson],'*', MUST_EXIST);
        }else{
            $this->moduleinstance =$moduleinstance;
        }
        $this->course = get_course($this->moduleinstance->course);
        if(!$context) {
            $cm         = get_coursemodule_from_instance('minilesson', $this->moduleinstance->id, $this->course->id, false, MUST_EXIST);
            $this->context =  \context_module::instance($cm->id);
        }else{
            $this->context = $context;
        }
        if(!empty($token)) {
            $this->token = $token;
        }
        $this->editoroptions = self::fetch_editor_options($this->course,$this->context);
        $this->filemanageroptions = self::fetch_filemanager_options($this->course);
        $this->forcetitles = $this->moduleinstance->showqtitles;
        $this->region = $this->moduleinstance->region;
        $this->language = $this->moduleinstance->ttslanguage;

    }

    /*
     * Returns the itemtype class for the itemtype
     */
    public static function get_itemtype_class($itemtype){
        $classname = '\\mod_minilesson\\local\\itemtype\\item_' . $itemtype;
        if(class_exists($classname)){
            return $classname;
        }
        return false;
    }


    public function set_token($token){
        $this->token = $token;
    }
    public function set_currentnumber($currentnumber){
        $this->currentnumber = $currentnumber;
    }
    
    public static function get_keycolumns(){
        $keycolumns =  [];
        $keycolumns['type']=['type'=>'string','optional'=>false,'default'=>'','dbname'=>'type'];
        $keycolumns['name']=['type'=>'string','optional'=>false,'default'=>'','dbname'=>'name'];
        $keycolumns['visible']=['type'=>'boolean','optional'=>true,'default'=>0,'dbname'=>'visible'];
        $keycolumns['instructions']=['type'=>'string','optional'=>true,'default'=>'', 'dbname'=>'iteminstructions'];
        $keycolumns['text']=['type'=>'string','optional'=>true,'default'=>'','dbname'=>'itemtext'];
        $keycolumns['textformat']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'itemtextformat'];
        $keycolumns['tts']=['type'=>'string','optional'=>true,'default'=>'','dbname'=>'itemtts'];
        $keycolumns['ttsvoice']=['type'=>'voice','optional'=>true,'default'=>'','dbname'=>'itemttsvoice'];
        $keycolumns['ttsoption']=['type'=>'voiceopts','optional'=>true,'default'=>0,'dbname'=>'itemttsoption'];
        $keycolumns['ttsautoplay']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'itemttsautoplay'];
        $keycolumns['textarea']=['type'=>'string','optional'=>true,'default'=>'','dbname'=>'itemtextarea'];
        $keycolumns['iframe']=['type'=>'string','optional'=>true,'default'=>'','dbname'=>constants::MEDIAIFRAME];
        $keycolumns['ytid']=['type'=>'string','optional'=>true,'default'=>'','dbname'=>'itemytid'];
        $keycolumns['ytstart']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'itemytstart'];
        $keycolumns['ytend']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'itemytend'];
        $keycolumns['audiofname']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'itemaudiofname'];
        $keycolumns['ttsdialog']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'itemttsdialog'];
        $keycolumns['ttsdialogopts']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'itemttsdialogopts'];
        $keycolumns['ttsdialogvoicea']=['type'=>'voice','optional'=>true,'default'=>null,'dbname'=>constants::TTSDIALOGVOICEA];
        $keycolumns['ttsdialogvoiceb']=['type'=>'voice','optional'=>true,'default'=>null,'dbname'=>constants::TTSDIALOGVOICEB];
        $keycolumns['ttsdialogvoicec']=['type'=>'voice','optional'=>true,'default'=>null,'dbname'=>constants::TTSDIALOGVOICEC];
        $keycolumns['ttsdialogvisible']=['type'=>'boolean','optional'=>true,'default'=>null,'dbname'=>constants::TTSDIALOGVISIBLE];
        $keycolumns['ttspassage']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'itemttspassage'];
        $keycolumns['ttspassageopts']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'itemttspassageopts'];
        $keycolumns['ttspassagevoice']=['type'=>'voice','optional'=>true,'default'=>null,'dbname'=>constants::TTSPASSAGEVOICE];
        $keycolumns['ttspassagespeed']=['type'=>'voiceopts','optional'=>true,'default'=>null,'dbname'=>constants::TTSPASSAGESPEED];
        $keycolumns['text1']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customtext1'];
        $keycolumns['text1format']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customtext1format'];
        $keycolumns['text2']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customtext2'];
        $keycolumns['text2format']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customtext2format'];
        $keycolumns['text3']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customtext3'];
        $keycolumns['text3format']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customtext3format'];
        $keycolumns['text4']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customtext4'];
        $keycolumns['text4format']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customtext4format'];
        $keycolumns['text5']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customtext5'];
        $keycolumns['text5format']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customtext5format'];
        $keycolumns['text6']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customtext6'];
        $keycolumns['data1']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customdata1'];
        $keycolumns['data2']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customdata2'];
        $keycolumns['data3']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customdata3'];
        $keycolumns['data4']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customdata4'];
        $keycolumns['data5']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customdata5'];
        $keycolumns['int1']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customint1'];
        $keycolumns['int2']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customint2'];
        $keycolumns['int3']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customint3'];
        $keycolumns['int4']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customint4'];
        $keycolumns['int5']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customint5'];
        $keycolumns['timelimit']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'timelimit'];
        $keycolumns['layout']=['type'=>'layout','optional'=>true,'default'=>0,'dbname'=>'layout'];
        $keycolumns['correctanswer']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'correctanswer'];
      //  $keycolumns['correctanswers']=['type'=>'string','optional'=>true,'default'=>'','dbname'=>'customtext1'];
        return $keycolumns;
    }

    public static function get_import_keycolumns(){
        $keycols = [];
        return $keycols;
    }

    /*
     * Validates the items data from the import record
     * most types should override this with specific validation code
     */
    public static function validate_import($newrecord,$cm){
        $error = new \stdClass();
        $error->col='';
        $error->message='';
        //check for errors here

        //return false to indicate no error
        return false;
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the item
     * @return array
     */
    public function export_for_template(\renderer_base $output){
        $testitem= new \stdClass();
        $testitem = $this->get_common_elements($testitem);
        $testitem = $this->get_text_answer_elements($testitem);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);

        return $testitem;
    }

    protected function get_common_elements($testitem){
        global $CFG;

        $itemrecord = $this->itemrecord;
        $editoroptions = $this->editoroptions;

        //the basic item attributes
        $testitem->number =  $this->currentnumber;
        $testitem->correctanswer =  $this->itemrecord->correctanswer;
        $testitem->id = $this->itemrecord->id;
        $testitem->type=$this->itemrecord->type;
        $testitem->name=$this->itemrecord->name;
        $testitem->timelimit=$this->itemrecord->timelimit;
        if($this->forcetitles){$testitem->title=$this->itemrecord->name;}
        $testitem->uniqueid=$this->itemrecord->type . $testitem->number;

        //Question instructions
        if(!empty($itemrecord->{constants::TEXTINSTRUCTIONS})) {
            $testitem->iteminstructions = $itemrecord->{constants::TEXTINSTRUCTIONS};
        }

        //Question Text
        $itemtext =  file_rewrite_pluginfile_urls($itemrecord->{constants::TEXTQUESTION},
            'pluginfile.php', $this->context->id,constants::M_COMPONENT,
            constants::TEXTQUESTION_FILEAREA, $testitem->id);
        $itemtext = format_text($itemtext, FORMAT_MOODLE, $editoroptions);
        if(!empty($itemtext)) {
            $testitem->itemtext = $itemtext;
        }

        //Question media embed
        if(!empty($itemrecord->{constants::MEDIAIFRAME}) && !empty(trim($itemrecord->{constants::MEDIAIFRAME}))){
            $testitem->itemiframe=$itemrecord->{constants::MEDIAIFRAME};
        }

        //Question media items (upload)
        $mediaurls =$this->fetch_media_urls(constants::MEDIAQUESTION,$itemrecord);
        if($mediaurls && count($mediaurls)>0){
            foreach($mediaurls as $mediaurl){
                $file_parts = pathinfo(strtolower($mediaurl));
                switch($file_parts['extension'])
                {
                    case "jpg":
                    case "jpeg":
                    case "png":
                    case "gif":
                    case "bmp":
                    case "svg":
                        $testitem->itemimage = $mediaurl;
                        break;

                    case "mp4":
                    case "mov":
                    case "webm":
                    case "ogv":
                        $testitem->itemvideo = $mediaurl;
                        break;

                    case "m4a":
                    case "mp3":
                    case "ogg":
                    case "wav":
                        $testitem->itemaudio = $mediaurl;
                        break;

                    default:
                        //do nothing
                }//end of extension switch
            }//end of for each
        }//end of if mediaurls

        //TTS Question
        if(!empty($itemrecord->{constants::TTSQUESTION}) && !empty(trim($itemrecord->{constants::TTSQUESTION}))){
            $testitem->itemttsaudio=$itemrecord->{constants::TTSQUESTION};
            $testitem->itemttsaudiovoice=$itemrecord->{constants::TTSQUESTIONVOICE};
            $testitem->itemttsoption=$itemrecord->{constants::TTSQUESTIONOPTION};
            $testitem->itemttsautoplay=$itemrecord->{constants::TTSAUTOPLAY};
        }
        //YT Clip
        if(!empty($itemrecord->{constants::YTVIDEOID}) &&!empty(trim($itemrecord->{constants::YTVIDEOID}))){
            $ytvideoid = trim($itemrecord->{constants::YTVIDEOID});
            //if its a YT URL we want to parse the id from it
            if(\core_text::strlen($ytvideoid)>11){
                $urlbits=[];
                preg_match('/^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/', $ytvideoid, $urlbits);
                if($urlbits && count($urlbits)>7){
                    $ytvideoid=$urlbits[7];
                }
            }
            //
            $testitem->itemytvideoid=$ytvideoid;
            $testitem->itemytvideostart=$itemrecord->{constants::YTVIDEOSTART};
            $testitem->itemytvideoend=$itemrecord->{constants::YTVIDEOEND};
        }
        //TTS Dialog
        if(!empty($itemrecord->{constants::TTSDIALOG}) && !empty(trim($itemrecord->{constants::TTSDIALOG}))){
            $itemrecord = utils::unpack_ttsdialogopts($itemrecord);
            $testitem->itemttsdialog=true;
            $testitem->itemttsdialogvisible=$itemrecord->{constants::TTSDIALOGVISIBLE};
            $dialoglines = explode(PHP_EOL,$itemrecord->{constants::TTSDIALOG});
            $linesdata=[];
            foreach($dialoglines as $theline){
                if(\core_text::strlen($theline)>1) {
                    $startchars = \core_text::substr($theline, 0, 2);
                    switch($startchars){
                        case 'A)':
                            $speaker="a";
                            $voice=$itemrecord->{constants::TTSDIALOGVOICEA};
                            $thetext = \core_text::substr($theline, 2);
                            break;
                        case 'B)':
                            $speaker="b";
                            $voice=$itemrecord->{constants::TTSDIALOGVOICEB};
                            $thetext = \core_text::substr($theline, 2);
                            break;
                        case 'C)':
                            $speaker="c";
                            $voice=$itemrecord->{constants::TTSDIALOGVOICEC};
                            $thetext = \core_text::substr($theline, 2);
                            break;
                        case '>>':
                            $speaker="soundeffect";
                            $voice="soundeffect";
                            $thetext = \core_text::substr($theline, 2);
                            break;
                        default:
                            //if it's just a new line for the previous voice
                            if(count($linesdata)>0){
                                $voice=$linesdata[count($linesdata)-1]->voice;
                                $speaker=$linesdata[count($linesdata)-1]->actor;
                                //if they never entered A) B) or C)
                            }else{
                                $voice=$itemrecord->{constants::TTSDIALOGVOICEA};
                                $speaker="a";
                            }
                            $thetext = $theline;

                    }
                    if(empty(trim($thetext))){continue;}
                    $lineset=new \stdClass();
                    $lineset->speaker=$speaker;
                    $lineset->speakertext=$thetext;
                    $lineset->voice=$voice;
                    $voiceoptions = constants::TTS_NORMAL;
                    if($lineset->voice=="soundeffect"){
                        $lineset->audiourl = $CFG->wwwroot  . '/' . constants::M_PATH . '/sounds/' . trim($thetext) . '.mp3';
                    }else {
                        $lineset->audiourl = utils::fetch_polly_url($this->token, 'useast1', $thetext, $voiceoptions, $voice);
                    }
                    $linesdata[] = $lineset;

                }

            }
            $testitem->ttsdialoglines = $linesdata;

        }// end of tts dialog

        //TTS Passage
        if(!empty($itemrecord->{constants::TTSPASSAGE}) && !empty(trim($itemrecord->{constants::TTSPASSAGE}))){
            $itemrecord = utils::unpack_ttspassageopts($itemrecord);
            $testitem->itemttspassage=true;
            $textlines = utils::split_into_sentences($itemrecord->{constants::TTSPASSAGE});
            $voice = $itemrecord->{constants::TTSPASSAGEVOICE};
            $voiceoptions = $itemrecord->{constants::TTSPASSAGESPEED};
            $linedatas=[];
            foreach($textlines as $theline){
                if(!empty(trim($theline))) {
                    $linedata=new \stdClass();
                    $linedata->sentence = $theline;
                    $linedata->audiourl = utils::fetch_polly_url($this->token, 'useast1', $theline, $voiceoptions, $voice);
                    $linedatas[]=$linedata;
                }
            }
            $testitem->ttspassagelines = $linedatas;
        }// end of tts dialog

        //Question TextArea
        if(!empty($itemrecord->{constants::QUESTIONTEXTAREA}) && !empty(trim($itemrecord->{constants::QUESTIONTEXTAREA}))){
            $testitem->itemtextarea=nl2br($itemrecord->{constants::QUESTIONTEXTAREA});
        }

        //show text prompt or dots, for listen and repeat really
        $testitem->show_text=$itemrecord->{constants::SHOWTEXTPROMPT};

        //For right to left languages we want to add the RTL direction and right justify.
        switch($this->moduleinstance->ttslanguage){
            case constants::M_LANG_ARAE:
            case constants::M_LANG_ARSA:
            case constants::M_LANG_FAIR:
            case constants::M_LANG_HEIL:
                $testitem->rtl=constants::M_CLASS . '_rtl';
                break;
            default:
                $testitem->rtl='';
        }

        return $testitem;
    }

    protected function get_text_answer_elements($testitem){
        $itemrecord = $this->itemrecord;
        //Text answer fields
        for($anumber=1;$anumber<=constants::MAXANSWERS;$anumber++) {
            if(!empty($itemrecord->{constants::TEXTANSWER . $anumber}) && !empty(trim($itemrecord->{constants::TEXTANSWER . $anumber}))) {
                $testitem->{'customtext' . $anumber} = $itemrecord->{constants::TEXTANSWER . $anumber};
            }
        }
        return $testitem;
    }

    protected function get_polly_options($testitem){

        //if we need polly then lets do that
        $testitem->usevoice=$this->itemrecord->{constants::POLLYVOICE};
        $testitem->voiceoption=$this->itemrecord->{constants::POLLYOPTION};
        return $testitem;
    }

    protected function set_layout($testitem){

        //vertical layout or horizontal layout determined by content options
        $textset = isset($testitem->itemtextarea) && !empty($testitem->itemtextarea);
        $imageset = isset($testitem->itemimage) && !empty($testitem->itemimage);
        $videoset =isset($testitem->itemvideo) && !empty($testitem->itemvideo);
        $iframeset =isset($testitem->itemiframe) && !empty($testitem->itemiframe);
        $ytclipset =isset($testitem->itemytvideoid) && !empty($testitem->itemytvideoid);

        //layout
        $testitem->layout=$this->itemrecord->{constants::LAYOUT};
        if($testitem->layout==constants::LAYOUT_AUTO) {
            //if its not a page or shortanswer, any big content item will make it horizontal layout
            if ($testitem->type !== constants::TYPE_PAGE && $testitem->type !== constants::TYPE_SHORTANSWER) {
                if ($textset || $imageset || $videoset || $iframeset || $ytclipset) {
                    $testitem->horizontal = true;
                }
            }
        }else{
            switch($testitem->layout){
                case constants::LAYOUT_HORIZONTAL:
                    $testitem->horizontal = true;
                    break;
                case constants::LAYOUT_VERTICAL:
                    $testitem->vertical = true;
                    break;
                case constants::LAYOUT_MAGAZINE:
                    $testitem->magazine = true;
                    break;
            }
        }
        return $testitem;
    }

    /*
     * Processes gap fill sentences : TO DO not implemented, implement this
     */
    protected function process_gapfill_sentences($sentences){
        $sentenceobjects = [];
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) {
                continue;
            }

            //TO DO replace [x] with gaps
            $prompt = $sentence;

            $s = new \stdClass();
            $s->index = $index;
            $s->indexplusone = $index + 1;
            $s->sentence = $sentence;
            $s->prompt = $prompt;
            $s->displayprompt = $prompt;
            $s->length = \core_text::strlen($s->sentence);

            $index++;
            $sentenceobjects[] = $s;
        }
        return $sentenceobjects;
    }

    /*
     * Processes gap fill sentences : TO DO not implemented, implement this
     */
    protected function process_typinggapfill_sentences($sentences) {
        return $this->parse_gapfill_sentences($sentences);
    }

    /*
     * Processes listening gap fill sentences : TO DO not implemented, implement this
     */
    protected function process_listeninggapfill_sentences($sentences) {
        return $this->parse_gapfill_sentences($sentences);
    }

    /*
     * Processes speaking gap fill sentences
     */
    protected function process_speakinggapfill_sentences($sentences) {
        return $this->parse_gapfill_sentences($sentences);
    }

    /*
     * Processes listening gap fill sentences : TO DO not implemented, implement this
     */
    protected function parse_gapfill_sentences($sentences) {
        $index = 0;
        $sentenceobjects = [];

        foreach ($sentences as $sentence) {
            $arr = explode('|', trim($sentence));
            $sentence = trim($arr[0] ?? '');
            $definition = trim($arr[1] ?? '');
            if (empty($sentence)) {
                continue;
            }

            $parsedstring = [];
            $started = false;
            $words = explode(' ', $sentence);
            $maskedwords = [];
            foreach ($words as $index => $word) {
                if (strpos($word, '[') !== false) {
                    $maskedwords[$index] = str_replace(['[', ']', ',', '.'], ['', '', '', ''], $word);
                }
            }
            $enc = mb_detect_encoding($sentence);
            $characters = utils::do_mb_str_split($sentence,1, $enc);
            //encoding parameter is required for < PHP 8.0
        //    $characters=str_split($sentence); //DEBUG ONLY - fails on multibyte characters
       //     $characters=mb_str_split($sentence); //DEBUG ONLY - - only exists on 7.4 and greater .. ie NOT for 7.3

            $wordindex = 0;
            foreach ($characters as $character) {
                if ($character === ' ') {
                    $wordindex++;
                }
                if ($character === '[') {
                    $started = true;
                    continue;
                }
                if ($character === ']') {
                    $started = false;
                    continue;
                }
                if (array_key_exists($wordindex, $maskedwords) && $character !== " " && $character !== "." ) {
                    if ($started) {
                        $parsedstring[] = ['index' => $wordindex, 'character' => $character, 'type' => 'input'];
                    } else {
                        $parsedstring[] = ['index' => $wordindex, 'character' => $character, 'type' => 'mtext'];
                    }
                } else {
                    $parsedstring[] = ['index' => $wordindex, 'character' => $character, 'type' => 'text'];
                }
            }
            $sentence = str_replace(['[', ']', ',', '.'], ['', '', '', ''], $sentence);
            $prompt = $sentence;

            $s = new \stdClass();
            $s->index = $index;
            $s->indexplusone = $index + 1;
            $s->sentence = $sentence;
            $s->prompt = $prompt;
            $s->definition = $definition;
            $s->displayprompt = $prompt;
            $s->length = \core_text::strlen($s->sentence);
            $s->parsedstring = $parsedstring;
            $s->words = $maskedwords;
            $sentenceobjects[] = $s;
            $index++;
        }

        return $sentenceobjects;
    }

    /*
     * Takes an array of sentences and phonetics for the same, and returns sentence objects with display and spoken and phonetic data
     *
     */
    protected function process_spoken_sentences($sentences,$phonetics, $dottify=false, $is_ssml=false){
        //build a sentences object for mustache and JS
        $index = 0;
        $sentenceobjects = [];
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) {
                continue;
            }

            //build prompt and displayprompt and sentence which could be different
            // prompt = audio_prompt
            // sentence = target_sentence ie what the student should say (correct response)
            // displayprompt = the text_prompt that we show the student before they speak

            //dottify = if we dont show the text and just show dots ..
            if($dottify){
                $prompt = $this->dottify_text($sentence);
                $displayprompt = $prompt;

            }else{

                //if we have a pipe prompt = array[0] and response = array[1]
                $sentencebits = explode('|', $sentence);
                if (count($sentencebits) > 1) {
                    $prompt = trim($sentencebits[0]);
                    $sentence = trim($sentencebits[1]);
                    if(count($sentencebits) >2){
                        $displayprompt = trim($sentencebits[2]);
                    }else{
                        $displayprompt = $prompt;
                    }
                } else {
                    $prompt = $sentence;
                    $displayprompt = $sentence;
                }
            }

            //we strip the HTML tags off if it is SSML
            //probably no harm in doing this if its SSML or not ...
            if($is_ssml) {
                $displayprompt = strip_tags($displayprompt );
                $sentence = strip_tags($sentence);
            }

            if ($this->language == constants::M_LANG_JAJP) {
                $sentence = $this->process_japanese_phonetics($sentence);
            }

            $s = new \stdClass();
            $s->index = $index;
            $s->indexplusone = $index + 1;
            $s->sentence = $sentence;
            $s->prompt = $prompt;
            $s->displayprompt = $displayprompt;
            $s->length = \core_text::strlen($s->sentence);

            //add phonetics if we have them
            if(isset($phonetics[$index]) && !empty($phonetics[$index])){
                $s->phonetic=$phonetics[$index];
            }else{
                $s->phonetic='';
            }

            $index++;
            $sentenceobjects[] = $s;
        }
        return $sentenceobjects;
    }

    //by default we do nothing, but for japanese listen_and_speak, dictation chat and shortanswer, this is overrridden
    protected function process_japanese_phonetics($sentence){
        return $sentence;
    }

    protected function set_cloudpoodll_details($testitem){
        global $USER,$CFG;

        $itemrecord = $this->itemrecord;
        $testitem->region =$this->region;
        $testitem->cloudpoodlltoken = $this->token;
        $testitem->wwwroot=$CFG->wwwroot;
        $testitem->language=$this->language;
        $testitem->hints='';
        $testitem->owner=hash('md5',$USER->username);
        $testitem->usevoice=$itemrecord->{constants::POLLYVOICE};
        $testitem->voiceoption=$itemrecord->{constants::POLLYOPTION};

        //TT Recorder stuff
        $testitem->waveheight = 75;
        //passagehash for several reasons could rightly be empty
        //if its full it will be region|hash eg tokyo|2353531453415134545
        //we just want the hash here
        $testitem->passagehash="";
        if(!empty($itemrecord->passagehash)){
            $hashbits = explode('|',$itemrecord->passagehash,2);
            if(count($hashbits)==2){
                $testitem->passagehash  = $hashbits[1];
            }
        }

        //API gateway URL
        $testitem->asrurl = utils::fetch_lang_server_url($this->region,'transcribe');
        //recording max time
        $testitem->maxtime = 15000;

        return $testitem;
    }

    protected function dottify_text($rawtext){
        $re = '/[^\'!"#$%&\\\\\'()\*+,\-\.\/:;<=> ?@\[\\\\\]\^_`{|}~\']/u';
        $subst = '•';

        $dots = preg_replace($re, $subst, $rawtext);
        return $dots;
    }

    protected function fetch_media_urls($filearea,$item){
        //get question audio div (not so easy)
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id,  constants::M_COMPONENT,$filearea,$item->id);
        $urls=[];
        foreach ($files as $file) {
            $filename = $file->get_filename();
            if($filename=='.'){continue;}
            $filepath = '/';
            $mediaurl = \moodle_url::make_pluginfile_url($this->context->id, constants::M_COMPONENT,
                $filearea, $item->id,
                $filepath, $filename);
            $urls[]= $mediaurl->__toString();

        }
        return $urls;
        // return "$this->context->id pp $filearea pp $item->id";
    }

    //public static function update_insert_item($minilesson, $data, $edit, $context, $cm ,$editoroptions, $filemanageroptions) {
    public function update_insert_item() {
        global $DB, $USER;

        $ret = new \stdClass;
        $ret->error = false;
        $ret->message = '';
        $ret->payload = null;
        $data = $this->itemrecord;

        $theitem = new \stdClass;
        $theitem->minilesson = $this->moduleinstance->id;
        if(!isset($theitem->id)&&isset($data->itemid)){
            $theitem->id = $data->itemid;
        }
        $theitem->visible = $data->visible;
        $theitem->itemorder = $data->itemorder;
        $theitem->type = $data->type;
        $theitem->name = $data->name;
        $theitem->phonetic = $data->phonetic;
        $theitem->passagehash = $data->passagehash;
        $theitem->modifiedby = $USER->id;
        $theitem->timemodified = time();

        //first insert a new item if we need to
        //that will give us a itemid, we need that for saving files
        if (empty($data->itemid)) {

            $theitem->{constants::TEXTQUESTION} = '';
            $theitem->timecreated = time();
            $theitem->createdby = $USER->id;

            //get itemorder
            $theitem->itemorder = self::fetch_next_item_order($this->moduleinstance->id);

            //create a rsquestionkey
            $theitem->rsquestionkey = self::create_itemkey();

            //try to insert it
            if (!$theitem->id = $DB->insert_record(constants::M_QTABLE, $theitem)) {
                $ret->error = true;
                $ret->message = "Could not insert minilesson item!";
                return $ret;
            }
        }//end of if empty($data->itemid)

        //handle all the text questions
        //if its an editor field, do this
        if (property_exists($data, constants::TEXTQUESTION . '_editor')) {
            $data = file_postupdate_standard_editor($data, constants::TEXTQUESTION, $this->editoroptions, $this->context,
                constants::M_COMPONENT, constants::TEXTQUESTION_FILEAREA, $theitem->id);
            $theitem->{constants::TEXTQUESTION} = $data->{constants::TEXTQUESTION};
            $theitem->{constants::TEXTQUESTION_FORMAT} = $data->{constants::TEXTQUESTION_FORMAT};
            //if its a text area field, do this
        } else if (property_exists($data, constants::TEXTQUESTION)) {
            $theitem->{constants::TEXTQUESTION} = $data->{constants::TEXTQUESTION};
        }

        //Question instructions
        if (property_exists($data, constants::TEXTINSTRUCTIONS)) {
            $theitem->{constants::TEXTINSTRUCTIONS} = $data->iteminstructions;
        }

        // Time limit.
        if (property_exists($data, constants::TIMELIMIT)) {
            $theitem->{constants::TIMELIMIT} = $data->{constants::TIMELIMIT};
        }

        //layout
        if (property_exists($data, constants::LAYOUT)) {
            $theitem->{constants::LAYOUT} = $data->{constants::LAYOUT};
        }else{
            $theitem->{constants::LAYOUT} = constants::LAYOUT_AUTO;
        }

        //Item media
        if (property_exists($data, constants::MEDIAQUESTION)) {
            file_save_draft_area_files($data->{constants::MEDIAQUESTION},
                $this->context->id, constants::M_COMPONENT,
                constants::MEDIAQUESTION, $theitem->id,
                $this->filemanageroptions);
        }

        //Item TTS
        if (property_exists($data, constants::TTSQUESTION)) {
            $theitem->{constants::TTSQUESTION} = $data->{constants::TTSQUESTION};
            if (property_exists($data, constants::TTSQUESTIONVOICE)) {
                $theitem->{constants::TTSQUESTIONVOICE} = $data->{constants::TTSQUESTIONVOICE};
            }else{
                $theitem->{constants::TTSQUESTIONVOICE} = 'Amy';
            }
            if (property_exists($data, constants::TTSQUESTIONOPTION)) {
                $theitem->{constants::TTSQUESTIONOPTION} = $data->{constants::TTSQUESTIONOPTION};
            }else{
                $theitem->{constants::TTSQUESTIONOPTION} = constants::TTS_NORMAL;
            }
            if (property_exists($data, constants::TTSAUTOPLAY)) {
                $theitem->{constants::TTSAUTOPLAY} = $data->{constants::TTSAUTOPLAY};
            }else{
                $theitem->{constants::TTSAUTOPLAY} = 0;
            }
        }

        //Item Text Area
        $edoptions = constants::ITEMTEXTAREA_EDOPTIONS;
        $edoptions['context']=$this->context;
        if (property_exists($data, constants::QUESTIONTEXTAREA . '_editor')) {
            $data->{constants::QUESTIONTEXTAREA. 'format'}=FORMAT_HTML;
            $data = file_postupdate_standard_editor($data, constants::QUESTIONTEXTAREA, $edoptions, $this->context,
                constants::M_COMPONENT, constants::TEXTQUESTION_FILEAREA, $theitem->id);
            $theitem->{constants::QUESTIONTEXTAREA} = trim($data->{constants::QUESTIONTEXTAREA});
        }

        //Item YT Clip
        if (property_exists($data, constants::YTVIDEOID)) {
            $theitem->{constants::YTVIDEOID} = $data->{constants::YTVIDEOID};
            if (property_exists($data, constants::YTVIDEOSTART)) {
                $theitem->{constants::YTVIDEOSTART} = $data->{constants::YTVIDEOSTART};
            }
            if (property_exists($data, constants::YTVIDEOEND)) {
                $theitem->{constants::YTVIDEOEND} = $data->{constants::YTVIDEOEND};
            }
        }

        //TTS Dialog
        if(property_exists($data,constants::TTSDIALOG) && $data->{constants::TTSDIALOG}!==null ){
            $theitem->{constants::TTSDIALOG} = $data->{constants::TTSDIALOG};
            $theitem->{constants::TTSDIALOGOPTS} = utils::pack_ttsdialogopts($data);
        }

        //TTS Passage
        if(property_exists($data,constants::TTSPASSAGE)&& $data->{constants::TTSPASSAGE}!==null ){
            $theitem->{constants::TTSPASSAGE} = $data->{constants::TTSPASSAGE};
            $theitem->{constants::TTSPASSAGEOPTS} = utils::pack_ttspassageopts($data);
        }

        //save correct answer if we have one
        if (property_exists($data, constants::CORRECTANSWER)) {
            $theitem->{constants::CORRECTANSWER} = $data->{constants::CORRECTANSWER};
        }

        //save correct answer if we have one
        if (property_exists($data, constants::CORRECTANSWER)) {
            $theitem->{constants::CORRECTANSWER} = $data->{constants::CORRECTANSWER};
        }

        //save text answers and other data in custom text
        //could be editor areas
        for ($anumber = 1; $anumber <= constants::MAXCUSTOMTEXT; $anumber++) {
            //if its an editor field, do this
            if (property_exists($data, constants::TEXTANSWER . $anumber . '_editor')) {
                $data = file_postupdate_standard_editor($data, constants::TEXTANSWER . $anumber, $this->editoroptions, $this->context,
                    constants::M_COMPONENT, constants::TEXTANSWER_FILEAREA . $anumber, $theitem->id);
                $theitem->{constants::TEXTANSWER . $anumber} = $data->{'customtext' . $anumber};
                $theitem->{constants::TEXTANSWER . $anumber . 'format'} = $data->{constants::TEXTANSWER . $anumber . 'format'};
                //if its a text field, do this
            } else if (property_exists($data, constants::TEXTANSWER . $anumber)) {
                $thetext = trim($data->{constants::TEXTANSWER . $anumber});
                //segment the text if it is japanese and not already segmented
                //TO DO: remove this
                /*
                if($minilesson->ttslanguage == constants::M_LANG_JAJP &&
                    ($data->type==CONSTANTS::TYPE_LISTENREPEAT ||
                        $data->type==CONSTANTS::TYPE_SPEECHCARDS ||
                        $data->type==CONSTANTS::TYPE_SHORTANSWER )){
                        if(strpos($thetext,' ')==false){
                            //  $thetext = utils::segment_japanese($thetext);
                        }
                }
                */
                $theitem->{constants::TEXTANSWER . $anumber} = $thetext;
            }
        }

        //we might have other customdata
        for ($anumber = 1; $anumber <= constants::MAXCUSTOMDATA; $anumber++) {
            if (property_exists($data, constants::CUSTOMDATA . $anumber)) {
                $theitem->{constants::CUSTOMDATA . $anumber} = $data->{constants::CUSTOMDATA . $anumber};
            }
        }

        //we might have custom int
        for ($anumber = 1; $anumber <= constants::MAXCUSTOMINT; $anumber++) {
            if (property_exists($data, constants::CUSTOMINT . $anumber)) {
                $theitem->{constants::CUSTOMINT . $anumber} = $data->{constants::CUSTOMINT . $anumber};
            }
        }


        //now update the db once we have saved files and stuff
        if (!$DB->update_record(constants::M_QTABLE, $theitem)) {
            $ret->error = true;
            $ret->message = "Could not update minilesson item!";
            return $ret;
        }else{
            $ret->item = $theitem;
            return $ret;
        }
    }//end of edit_insert_question

    public static function delete_item($itemid, $context)
    {
        global $DB;
        $ret = false;

        if (!$DB->delete_records(constants::M_QTABLE, array('id' => $itemid))) {
            print_error("Could not delete item");
            return $ret;
        }
        //remove files
        $fs = get_file_storage();

        $fileareas = array(constants::TEXTPROMPT_FILEAREA,
            constants::TEXTPROMPT_FILEAREA . '1',
            constants::TEXTPROMPT_FILEAREA . '2',
            constants::TEXTPROMPT_FILEAREA . '3',
            constants::TEXTPROMPT_FILEAREA . '4',
            constants::MEDIAQUESTION);

        foreach ($fileareas as $filearea) {
            $fs->delete_area_files($context->id, constants::M_COMPONENT, $filearea, $itemid);
        }
        $ret = true;
        return $ret;
    }


    public static function fetch_editor_options($course, $modulecontext)
    {
        $maxfiles = 99;
        $maxbytes = $course->maxbytes;
        return array('trusttext' => 0,'noclean'=>1, 'subdirs' => true, 'maxfiles' => $maxfiles,
            'maxbytes' => $maxbytes, 'context' => $modulecontext);
    }

    public static function fetch_filemanager_options($course, $maxfiles = 1)
    {
        $maxbytes = $course->maxbytes;
        return array('subdirs' => true, 'maxfiles' => $maxfiles, 'maxbytes' => $maxbytes, 'accepted_types' => array('audio', 'video','image'));
    }

    //fetch the next item order in the list of items
    protected static function fetch_next_item_order($minilessonid){
        global $DB;

        $allitems = $DB->get_records(constants::M_QTABLE, ['minilesson' => $minilessonid],'itemorder ASC');
        if($allitems &&count($allitems) > 0 ){
            $lastitem = array_pop($allitems);
            $itemorder = $lastitem->itemorder + 1;
        }else{
            $itemorder = 1;
        }
        return $itemorder;
    }

    //creates a "unique" item key so that backups and restores won't stuff things
    public static function create_itemkey()
    {
        global $CFG;
        $prefix = $CFG->wwwroot . '@';
        return uniqid($prefix, true);
    }



    /*
  * Remove any accents and chars that would mess up the transcript//passage matching
  */
    public function deaccent(){
        if($this->needs_speechrec) {
            $this->itemrecord->customtext1 = utils::remove_accents_and_poormatchchars($this->itemrecord->customtext1,$this->moduleinstance->ttslanguage);
        }
    }


    public function update_create_langmodel($olditemrecord){
        //if we need to generate a Coqui model for this, then lets do that now:
        //we want to process the hashcode and lang model if it makes sense
        $newitem = $this->itemrecord;
        if($this->needs_speechrec) {

                $passage = $newitem->customtext1;

                if (utils::needs_lang_model($this->moduleinstance,$passage)) {
                    //lets assign a default passage hash
                    if($olditemrecord) {
                        $this->itemrecord->passagehash = $olditemrecord->passagehash;
                    }else{
                        $this->itemrecord->passagehash ="";
                    }

                    //then fetch a new passage hash and see if we need to update it on the servers
                    $newpassagehash = utils::fetch_passagehash($this->language,$passage);
                    if ($newpassagehash) {
                        //check if it has changed, if its a brand new one, if so register a langmodel
                        if (!$olditemrecord || $olditemrecord->passagehash != ($this->region . '|' . $newpassagehash)) {

                            //build a lang model
                            $ret = utils::fetch_lang_model($passage, $this->language, $this->region);

                            //for doing a dry run
                            //$ret=new \stdClass();
                            //$ret->success=true;

                            if ($ret && isset($ret->success) && $ret->success) {
                                $this->itemrecord->passagehash = $this->region . '|' . $newpassagehash;
                                return true;
                            }
                        }
                    }
                }else{
                    $this->itemrecord->passagehash ='';
                }
        }else{
            $this->itemrecord->passagehash ='';
        }
        return false;
    }

    //we want to generate a phonetics if this is phonetic'able
    public function update_create_phonetic($olditemrecord){
        //if we have an old item, set the default return value to the current phonetic value
        //we will update it if the text has changed
        $newitem = $this->itemrecord;
        if($olditemrecord) {
            $thephonetics = $olditemrecord->phonetic;
        }else{
            $thephonetics ='';
        }

        if($this->needs_speechrec) {

                $newpassage = $newitem->customtext1;
                if($olditemrecord!==false) {
                    $oldpassage = $olditemrecord->customtext1;
                }else{
                    $oldpassage='';
                }

                if ($newpassage !== $oldpassage) {

                    $segmented=true;
                    $sentences=explode(PHP_EOL,$newpassage);
                    $allphonetics =[];
                    foreach($sentences as $sentence) {
                        list($thephones)  = utils::fetch_phones_and_segments($sentence, $this->language, 'tokyo', $segmented);
                        if(!empty($thephones)) {
                            $allphonetics[] = $thephones;
                        }
                    }

                    //build the final phonetics
                    if(count($allphonetics)>0) {
                        $thephonetics = implode(PHP_EOL, $allphonetics);
                    }
                }

        }
        $this->itemrecord->phonetic= $thephonetics;
        return $thephonetics;
    }


}
