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
 * Renderable class for a listening gap fill item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class  item_passagegapfill extends item {

    //the item type
    public const ITEMTYPE = constants::TYPE_PGAPFILL;


    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output){

        $testitem = new \stdClass();
        $testitem = $this->get_common_elements($testitem);
        $testitem = $this->get_text_answer_elements($testitem);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);


        // Passage Text
        $passagetext = $this->itemrecord->{constants::PASSAGEGAPFILL_PASSAGE};
        $plaintext = str_replace(['[', ']'], ['', ''], $passagetext);
        $passagetextwithnewlines = nl2br(s($passagetext));

        // Process the passage text to create the gaps and info that the mustache template and javascript needs.
        // we split on square brackets, so that we can identify the chunks that are to be replaced with gaps.
        $parsedchunks = [];
        $chunks = preg_split('/(\[|\])/u', $passagetextwithnewlines, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $inside = false;
        $index = -1;
        $wordindex = -1;
        foreach ($chunks as $chunk) {
            if ($chunk === '[') {
                $inside = true;
                continue;
            } else if ($chunk === ']') {
                $inside = false;
                continue;
            } else if ($inside) {
                // This is the bracketed word – create placeholder
                $placeholder = \core_text::substr($chunk, 0, 1) . str_repeat('&#x2022;', mb_strlen($chunk) - 1);
                $text = $chunk;
                $isgap = true;
                $index++;
                $wordindex++;
            } else {
                // Regular text (including punctuation, partial words, etc.)
                $placeholder = '';
                $text = $chunk;
                $isgap = false;
                $index++;
            }

            switch($this->language){
                case 'ar-SA':
                case 'ar-AE':
                case 'fa-IR':
                case 'he-IL':
                case 'ps-AF':
                    $textpadding = 2; // RTL langs seem to be wider and need more padding for proper display.
                    break;
                default:
                    $textpadding = 1;
            }
            $parsedchunks[$index] = [
                'wordindex' => $wordindex,
                'text' => $text,
                'placeholder' => $placeholder,
                'isgap' => $isgap,
                'textlength' => mb_strlen($text),
                'paddedtextlength' => mb_strlen($text) + $textpadding,
            ];
        }
        $passagedata = ['rawtext' => $passagetext, 'plaintext' => $plaintext, 'chunks' => $parsedchunks];
        $testitem->passagedata = $passagedata;

        //Item audio
        $testitem->passageaudio = utils::fetch_polly_url($this->token, $this->region,
            $plaintext, $this->itemrecord->{constants::POLLYOPTION},
            $this->itemrecord->{constants::POLLYVOICE});

        // Cloudpoodll
        $testitem = $this->set_cloudpoodll_details($testitem);
        // Hints gone from function so regain it here
        $testitem->hints = $this->itemrecord->{constants::PASSAGEGAPFILL_HINTS} == 0 ? false : $this->itemrecord->{constants::PASSAGEGAPFILL_HINTS};
        $testitem->althintstring = get_string('anotherhint', constants::M_COMPONENT);
        $testitem->penalizehints = $this->itemrecord->{constants::PENALIZEHINTS} == 1;
        return $testitem;
    }

    public static function validate_import($newrecord,$cm){
        $error = new \stdClass();
        $error->col='';
        $error->message='';

        if($newrecord->{constants::PASSAGEGAPFILL_PASSAGE}==''){
            $error->col=constants::PASSAGEGAPFILL_PASSAGE;
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
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt', 'type' => 'voiceopts', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYOPTION];
        $keycols['text5'] = ['jsonname' => 'promptvoice', 'type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYVOICE];
        $keycols['text1'] = ['jsonname' => 'sentences', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => constants::PASSAGEGAPFILL_PASSAGE];
        $keycols['int5'] = ['jsonname' => 'hidestartpage', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::PASSAGEGAPFILL_HINTS];
         $keycols['int2'] = ['jsonname' => 'penalizehints', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::PENALIZEHINTS];
        return $keycols;
    }


}
