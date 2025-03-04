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
 * Renderable class for a shortanswer item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_shortanswer extends item {

    //the item type
    public const ITEMTYPE = constants::TYPE_SHORTANSWER;


    /**
     * The class constructor.
     *
     */
    public function __construct($itemrecord, $moduleinstance=false, $context=false){
        parent::__construct($itemrecord, $moduleinstance, $context);
        $this->needs_speechrec=true;
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output){

        $testitem= new \stdClass();
        $testitem = $this->get_common_elements($testitem);
        $testitem = $this->get_text_answer_elements($testitem);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);
        $testitem->alternates = $this->itemrecord->{constants::ALTERNATES};

        //sentences
        $sentences = [];
        if(isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->customtext1);
        }
        //build sentence objects containing display and phonetic text
        $testitem->phonetic=$this->itemrecord->phonetic;
        if(!empty($testitem->phonetic)) {
            $phonetics = explode(PHP_EOL, $testitem->phonetic);
        }else{
            $phonetics=[];
        }
        $is_ssml=$testitem->voiceoption==constants::TTS_SSML;
        $dottify=false;
        $testitem->sentences = $this->process_spoken_sentences($sentences,$phonetics,$dottify,$is_ssml);

        // Do we need a streaming token?
        $alternatestreaming = get_config(constants::M_COMPONENT, 'alternatestreaming');
        $isenglish = strpos($this->moduleinstance->ttslanguage, 'en') === 0;
        if ($isenglish) {
            $testitem->streamingtoken = utils::fetch_streaming_token($this->moduleinstance->region);
            if($alternatestreaming){
                $testitem->forcestreaming = true;
            }
        }

        //cloudpoodll
        $testitem = $this->set_cloudpoodll_details($testitem);
        return $testitem;
    }

    public static function validate_import($newrecord,$cm){
        $error = new \stdClass();
        $error->col='';
        $error->message='';

        if($newrecord->customtext1==''){
            $error->col='customtext1';
            $error->message=get_string('error:emptyfield',constants::M_COMPONENT);
            return $error;
        }

        //return false to indicate no error
        return false;
    }

    /*
* This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
*/
    public static function get_keycolumns(){
        //get the basic key columns and customize a little for instances of this item type
        //get the basic key columns and customize a little for instances of this item type
       $keycols = parent::get_keycolumns();
       $keycols['text1']=['jsonname'=>'sentences','type'=>'stringarray','optional'=>true,'default'=>[],'dbname'=>'customtext1'];
       $keycols['text2'] = ['jsonname' => 'alternates', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => constants::ALTERNATES];
        return $keycols;
    }

}
