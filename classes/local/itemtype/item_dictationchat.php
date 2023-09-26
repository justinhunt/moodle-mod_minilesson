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
 * Renderable class for a dication chat item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_dictationchat extends item {

    //the item type
    public const ITEMTYPE = constants::TYPE_DICTATIONCHAT;

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
        $testitem->sentences = $this->process_spoken_sentences($sentences,$phonetics,$dottify, $is_ssml);

        //cloudpoodll
        $testitem = $this->set_cloudpoodll_details($testitem);

        return $testitem;
    }

    //overriding to get jp phonemes
    //If this is Japanese and a'chat' activity, the display sentence will be read as is
    // but the sentence we show on screen as the students entry needs to be broken into "words"
    //so we process it. In listen and speak it still shows the target, so its word'ified.
    //speechcards we do not give word level feedback. so we do nothing special
    //key point is to pass unwordified passage to compare_passage_transcipt ajax.
    protected function process_japanese_phonetics($sentence){
        // sadly this segmentation algorithm mismatches with server based one we need for phonetics
        //so we are not using it. We ought to save the segment rather than call each time
        // 初めまして =>(1) はじめまし て　＆　(2) はじめま　して
        //はなしてください=>(1)はな　して　く　だ　さい & (2)はな　して　ください
        //  $sentence = utils::segment_japanese($sentence);
        //TO DO save segments and not collect them at runtime
        list($phones,$sentence) = utils::fetch_phones_and_segments($sentence,$this->moduleinstance->ttslanguage,$this->moduleinstance->region);
        return $sentence;
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
        $keycols = parent::get_keycolumns();
        $keycols['text5']=['type'=>'voice','optional'=>true,'default'=>null,'dbname'=>constants::POLLYVOICE];
        $keycols['int4']=['type'=>'voiceopts','optional'=>true,'default'=>null,'dbname'=>constants::POLLYOPTION];
        return $keycols;
    }


}
