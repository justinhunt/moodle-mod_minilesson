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
 * Renderable class for a multichoice item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_multichoice extends item {

    //the item type
    public const ITEMTYPE = constants::TYPE_MULTICHOICE;

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        $itemrecord = $this->itemrecord;
        $testitem = new \stdClass();
        $testitem = $this->get_common_elements($testitem);
        $testitem = $this->get_text_answer_elements($testitem);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);
        //multichoice also needs sentences if we are listening. Its a bit of double up but we do that here.
        $testitem->sentences = [];
        $testitem->imagecontent = false;
        $testitem->audiocontent = false;
        switch ($itemrecord->{constants::LISTENORREAD}) {
            case constants::LISTENORREAD_LISTEN:
            case constants::LISTENORREAD_LISTENANDREAD:
                $testitem->audiocontent = true;
                break;
            case constants::LISTENORREAD_IMAGE:
                $testitem->imagecontent = true;
                break;
        }

        for ($anumber = 1; $anumber <= constants::MAXANSWERS; $anumber++) {
            // If we have an image we fetch it.
            $themediaurl = '';
            $sentencetext = '';
            if ($testitem->imagecontent) {
                $mediaurls = $this->fetch_media_urls(constants::FILEANSWER . $anumber, $itemrecord);
                if ($mediaurls && count($mediaurls) > 0) {
                    $themediaurl = $mediaurls[0];
                }
            }
            // If we have a sentence, we fetch it.
            if (!empty(trim($itemrecord->{constants::TEXTANSWER . $anumber}))) {
                $sentencetext = trim($itemrecord->{constants::TEXTANSWER . $anumber});
            }

            // If we have a sentence or an image, we add an answer to the mustache template data.
            if (!empty($sentencetext) || !empty($themediaurl)) {
                $sentence = $sentencetext;

                $s = new \stdClass();
                $s->index = $anumber - 1;
                $s->indexplusone = $anumber;
                $s->sentence = $sentence;
                $s->length = \core_text::strlen($sentence);

                if ($itemrecord->{constants::LISTENORREAD} == constants::LISTENORREAD_LISTEN) {
                    $s->prompt = $this->dottify_text($sentence);
                } else {
                    $s->prompt = $sentence;
                }
                if (!empty($themediaurl)) {
                    $s->mediaurl = $themediaurl;
                }
                $testitem->sentences[] = $s;
            }
        }

        //multichoice also has a confirm choice option we need to include
        $testitem->confirmchoice = $itemrecord->{constants::CONFIRMCHOICE};

        return $testitem;
    }

    public static function validate_import($newrecord,$cm){
        $error = new \stdClass();
        $error->col='';
        $error->message='';
 
/* The presence of images now means this check is not valid (no text + image is a possibility)
        if($newrecord->customtext1==''){
            $error->col='customtext1';
            $error->message=get_string('error:emptyfield',constants::M_COMPONENT);
            return $error;
        }
        if($newrecord->customtext2==''){
            $error->col='customtext2';
            $error->message=get_string('error:emptyfield',constants::M_COMPONENT);
            return $error;
        }

        if(!isset($newrecord->{'customtext' . $newrecord->correctanswer}) || $newrecord->{'customtext' . $newrecord->correctanswer}==''){
            $error->col='correctanswer';
            $error->message=get_string('error:correctanswer',constants::M_COMPONENT);
            return $error;
        }
*/
        //return false to indicate no error
        return false;
    }

    /*
* This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
*/
    public static function get_keycolumns(){
        //get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['text5']=['jsonname'=>'promptvoice','type'=>'voice','optional'=>true,'default'=>null,'dbname'=>constants::POLLYVOICE];
        $keycols['int4']=['jsonname'=>'promptvoiceopt','type'=>'voiceopts','optional'=>true,'default'=>null,'dbname'=>constants::POLLYOPTION];
        $keycols['int3']=['jsonname'=>'confirmchoice','type'=>'boolean','optional'=>true,'default'=>0,'dbname'=>constants::CONFIRMCHOICE];
        $keycols['int2']=['jsonname'=>'listenorread','type'=>'int','optional'=>true,'default'=>0,'dbname'=>constants::LISTENORREAD]; //not boolean ..
        for ($i=1;$i<=constants::MAXANSWERS;$i++){
            $keycols['fileanswer'.($i+constants::MAXANSWERS)]=['jsonname'=>constants::FILEANSWER.$i,'type'=>'anonymousfile','optional'=>true,'default'=>null,'dbname'=>false];
        }
        return $keycols;
    }

}
