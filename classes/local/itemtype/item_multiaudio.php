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
 * Renderable class for a multiaudio item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_multiaudio extends item
{

    //the item type
    public const ITEMTYPE = constants::TYPE_MULTIAUDIO;


    /**
     * The class constructor.
     *
     */
    public function __construct($itemrecord, $moduleinstance = false, $context = false)
    {
        parent::__construct($itemrecord, $moduleinstance, $context);
        $this->needs_speechrec = true;
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output)
    {

        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);

        // Sentences.
        $sentences = [];
        if (isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->{constants::TEXTANSWER . 1});
        }

        // Build sentence objects containing display and phonetic text.
        $testitem->phonetic = $this->itemrecord->phonetic;
        if (!empty($testitem->phonetic)) {
            $phonetics = explode(PHP_EOL, $testitem->phonetic);
        } else {
            $phonetics = [];
        }
        $is_ssml = $testitem->voiceoption == constants::TTS_SSML;
        $dottify = $this->itemrecord->{constants::SHOWTEXTPROMPT} == constants::TEXTPROMPT_DOTS;
        $testitem->sentences = $this->process_spoken_sentences($sentences, $phonetics, $dottify, $is_ssml);

        // Do we need a streaming token?
        $alternatestreaming = get_config(constants::M_COMPONENT, 'alternatestreaming');
        $isenglish = strpos($this->moduleinstance->ttslanguage, 'en') === 0;
        if ($isenglish) {
            $tokenobject = utils::fetch_streaming_token($this->moduleinstance->region);
            if ($tokenobject) {
                $testitem->speechtoken = $tokenobject->token;
                $testitem->speechtokenvalidseconds = $tokenobject->validseconds;
                 $testitem->speechtokentype = $tokenobject->tokentype;
            } else {
                $testitem->speechtoken = false;
                $testitem->speechtokenvalidseconds = 0;
                $testitem->speechtokentype = '';
            }
            if ($alternatestreaming) {
                $testitem->forcestreaming = true;
            }
        }

        //cloudpoodll
        $testitem = $this->set_cloudpoodll_details($testitem);

        return $testitem;
    }

    //overriding to get jp phonemes.
    // This is just zenkaku to hankaku for comparison of numbers
    protected function process_japanese_phonetics($sentence, $thephonetic = false)
    {
        $sentence = mb_convert_kana($sentence, "n");
        return $sentence;
    }

    /*
     * Remove any accents and chars that would mess up the transcript/passage matching
     */
    public function deaccent()
    {
        $this->itemrecord->customtext1 = utils::remove_accents_and_poormatchchars($this->itemrecord->customtext1, $this->moduleinstance->ttslanguage);
    }

    public function update_create_langmodel($olditemrecord)
    {
        //if we need to generate a DeepSpeech model for this, then lets do that now:
        //we want to process the hashcode and lang model if it makes sense
        $thepassagehash = '';
        $newitem = $this->itemrecord;

        $passage = $newitem->customtext1;

        if (utils::needs_lang_model($this->moduleinstance, $passage)) {
            $newpassagehash = utils::fetch_passagehash($this->language, $passage);
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
            //if we get here just set the new passage hash to the existing one
            if ($olditemrecord) {
                $this->itemrecord->passagehash = $olditemrecord->passagehash;
            } else {
                //This would happen if the user changed region, forcing an update, but there was no valid cloud poodll token
                $this->itemrecord->passagehash = '';
            }
        } else {
            //I think this will never get here
            $this->itemrecord->passagehash = '';
        }
        return false;
    }

    /*
     * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
     */
    public static function get_keycolumns()
    {
        //get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['text1'] = ['jsonname' => 'answers', 'type' => 'stringarray', 'optional' => false, 'default' => [], 'dbname' => 'customtext1'];
        $keycols['text5'] = ['jsonname' => 'promptvoice', 'type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYVOICE];
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt', 'type' => 'voiceopts', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYOPTION];
        $keycols['int1'] = ['jsonname' => 'showtextprompt', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::SHOWTEXTPROMPT];
        return $keycols;
    }

    /*
     * This is for use with importing, validating submitted data in each column
     */
    public static function validate_import($newrecord, $cm)
    {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if ($newrecord->customtext1 == '') {
            $error->col = 'customtext1';
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        if (!isset($newrecord->{'customtext' . $newrecord->correctanswer}) || $newrecord->{'customtext' . $newrecord->correctanswer} == '') {
            $error->col = 'correctanswer';
            $error->message = get_string('error:correctanswer', constants::M_COMPONENT);
            return $error;
        }

        //return false to indicate no error
        return false;
    }

    /*
     * This function return the prompt that the generate method requires for listening gap fill items.
     */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod)
    {
        switch ($generatemethod) {

            case 'extract':
                $prompt = "Create a multichoice question(text) and a one dimensional array of 4 answers (answers) in {language} suitable for {level} level learners to test the learner's understanding of the following passage: [{text}] ";
                $prompt .= "Also specify the correct answer as a number 1-4 in 'correctanswer'. ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Create a multichoice question(text) and a one dimensional array of 4 answers (answers) in {language} suitable for {level} level learners on the topic of: [{topic}] ";
                $prompt .= "Also specify the correct answer as a number 1-4 in 'correctanswer'. ";
                break;
        }
        return $prompt;
    }

    public function upgrade_item($oldversion)
    {
        global $DB;

        $success = true;
        if ($oldversion < 2025071305) {

            // The original multiadio stored each answer in a separate field.
            // We need to convert that to the new format which is a single field with answers separated
            // by a newline character.
            $sentences = [];

            for ($anumber = 1; $anumber <= constants::MAXANSWERS; $anumber++) {
                // If we have a sentence, we fetch it, and then clear the field.
                if (!empty(utils::super_trim($this->itemrecord->{constants::TEXTANSWER . $anumber}))) {
                    $sentences[] = utils::super_trim($this->itemrecord->{constants::TEXTANSWER . $anumber});
                    if ($anumber > 1) {
                        // Clear the old field, but it's possible that the format is already correct.
                        // So we do not want to overwrite field 1 yet.
                        $this->itemrecord->{constants::TEXTANSWER . $anumber} = '';
                    }
                }

            }
            if (count($sentences) < 2) {
                // If we have no sentences from the old fields lets not update the record.
                return true;
            }
            $allsentences = implode(PHP_EOL, $sentences);
            $this->itemrecord->{constants::TEXTANSWER . 1} = $allsentences;
            $success = $DB->update_record(constants::M_QTABLE, $this->itemrecord);
        }

        return $success;
    }

}
