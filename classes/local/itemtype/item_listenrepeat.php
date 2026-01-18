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

/**
 * Renderable class for a listenrepeat item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_listenrepeat extends item {

    // the item type
    public const ITEMTYPE = constants::TYPE_LISTENREPEAT;


    /**
     * The class constructor.
     *
     */
    public function __construct($itemrecord, $moduleinstance=false, $context=false) {
        parent::__construct($itemrecord, $moduleinstance, $context);
        $this->needs_speechrec = true;
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output) {

        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);
        $testitem->alternates = $this->itemrecord->{constants::ALTERNATES};
        $testitem->hidestartpage = $this->itemrecord->{constants::GAPFILLHIDESTARTPAGE} == 1;

        // sentences
        $sentences = [];
        if (isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->customtext1);
        }
        // build sentence objects containing display and phonetic text
        $testitem->phonetic = $this->itemrecord->phonetic;
        if (!empty($testitem->phonetic)) {
            $phonetics = explode(PHP_EOL, $testitem->phonetic);
        } else {
            $phonetics = [];
        }
        $isssml = $testitem->voiceoption == constants::TTS_SSML;
        $dottify = false;
        $testitem->sentences = $this->process_spoken_sentences($sentences, $phonetics, $dottify, $isssml);

        // Do we need a streaming token?
        $alternatestreaming = get_config(constants::M_COMPONENT, 'alternatestreaming');
        $isenglish = strpos($this->moduleinstance->ttslanguage, 'en') === 0;
        if ($isenglish || true) {
            $tokenobject = utils::fetch_streaming_token($this->moduleinstance->region);
            if ($tokenobject) {
                $testitem->speechtoken = $tokenobject->token;
                $testitem->speechtokenregion = $tokenobject->region;
                $testitem->speechtokenvalidseconds = $tokenobject->validseconds;
                 $testitem->speechtokentype = $tokenobject->tokentype;
            } else {
                $testitem->speechtoken = false;
                $testitem->speechtokenregion = '';
                $testitem->speechtokenvalidseconds = 0;
                $testitem->speechtokentype = '';
            }
            if ($alternatestreaming) {
                $testitem->forcestreaming = true;
            }
        }

        // cloudpoodll
        $testitem = $this->set_cloudpoodll_details($testitem);
        $testitem->newui = true;
        return $testitem;
    }

    /* We need to get segmented sentences for Japanese text, ie it has to be wordified.
    * This is because we get it back from transcription wordified so we can mark up "correct" and "incorrect" words
    * We only do this for Japanese as it is the only language we support that does not use spaces to separate words
    * (sorry Korean and Chinese speakers :) )
    * We store the segmented sentence in the phonetic field, separated by || from the phonetic text. But previously
    * we fetched it at runtime so we look out for data that has not been updated to store the segmented text
    */
    protected function process_japanese_phonetics($sentence, $thephonetics = false) {
        // We have a local segmentation algorythm utils:segment_japanese but
        // sadly this segmentation algorithm mismatches with server based one we need for phonetics
        // so we are not using it. It looks like this
        // 初めまして =>(1) はじめまし て　＆　(2) はじめま　して
        // はなしてください=>(1)はな　して　く　だ　さい & (2)はな　して　ください
        if($thephonetics) {
            $psarray = explode('|#', $thephonetics);
            $segmentedsentence = array_key_exists(1, $psarray) ? utils::super_trim($psarray[1]) : '';
            if (!empty($segmentedsentence)) {
                return $segmentedsentence;
            }
        }

        // Oh well, lets just fetch the segments now since we could not get the saved ones
        list($phones, $sentence) = utils::fetch_phones_and_segments($sentence, $this->moduleinstance->ttslanguage, $this->moduleinstance->region);
        return $sentence;

    }

    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if($newrecord->customtext1 == ''){
            $error->col = 'customtext1';
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        // return false to indicate no error
        return false;
    }

    /*
    * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
    */
    public static function get_keycolumns() {
        // get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt', 'type' => 'voiceopts', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYOPTION];
        $keycols['text5'] = ['jsonname' => 'promptvoice', 'type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYVOICE];
        $keycols['int1'] = ['jsonname' => 'showtextprompt', 'type' => 'boolean', 'optional' => true, 'default' => constants::TEXTPROMPT_WORDS, 'dbname' => constants::SHOWTEXTPROMPT];
        $keycols['text1'] = ['jsonname' => 'sentences', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => 'customtext1'];
        $keycols['text2'] = ['jsonname' => 'alternates', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => constants::ALTERNATES];
        $keycols['fileanswer_audio'] = ['jsonname' => constants::FILEANSWER.'1_audio', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        $keycols['fileanswer_image'] = ['jsonname' => constants::FILEANSWER.'1_image', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        return $keycols;
    }

     /*
    This function return the prompt that the generate method requires. 
    */
    public static function aigen_fetch_prompt ($itemtemplate, $generatemethod) {
        switch($generatemethod) {

            case 'extract':
                $prompt = "Extract a 1 dimensional array of 4 sentences from the following {language} text: [{text}]. ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Generate a 1 dimensional array of 4 sentences in {language} suitable for {level} level learners on the topic of: [{topic}] ";
                break;
        }
        return $prompt;
    }

}
