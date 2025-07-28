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
 * Renderable class for a wordshuffle item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_wordshuffle extends item
{

    //the item type
    public const ITEMTYPE = constants::TYPE_WORDSHUFFLE;

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output)
    {
        $itemrecord = $this->itemrecord;
        $testitem = new \stdClass();
        $testitem = $this->get_common_elements($testitem);
        $testitem = $this->get_text_answer_elements($testitem);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);
        // Word Shuffle may need sentences if we are listening. Its a bit of double up but we do that here.
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

        // Sentences.
        $sentences = [];
        if (isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->{constants::TEXTANSWER . 1});
        }
        // Image URLs.
        $imageurls = [];
        if ($testitem->imagecontent) {
            $imageurls = $this->fetch_sentence_media('image', 1);
        }
        // Audio URLs.
        $audiourls = [];
        if ($testitem->audiocontent) {
            $audiourls = $this->fetch_sentence_media('audio', 1);
        }

        for ($anumber = 0; $anumber < count($sentences); $anumber++) {
            $theimageurl = '';
            $theaudiourl = '';
            $sentencetext = '';

            // If we have a sentence, we fetch it.
            if (isset($sentences[$anumber]) && !empty(trim($sentences[$anumber]))) {
                $sentencetext = trim($sentences[$anumber]);
            }

            // If we have an image, we fetch it.
            if ($testitem->imagecontent) {
                if (isset($imageurls[$anumber+1]) && !empty($imageurls[$anumber+1])) {
                    $theimageurl = $imageurls[$anumber+1];
                }
            }

            // If we have an audio, we fetch it.
            if ($testitem->audiocontent) {
                if (isset($audiourls[$anumber+1]) && !empty($audiourls[$anumber+1])) {
                    $theaudiourl = $audiourls[$anumber+1];
                } else {
                    // If we have no custom audio then we use the polly audio.
                    if (!empty($sentencetext)) {
                        $theaudiourl = utils::fetch_polly_url(
                            $this->token,
                            $this->region,
                            $sentencetext ,
                            $this->itemrecord->{constants::POLLYOPTION},
                            $this->itemrecord->{constants::POLLYVOICE}
                        );
                    }
                }
            }

            // If we have a sentence or an image, we add an answer to the mustache template data.
            if (!empty($sentencetext)) {
                $sentence = $sentencetext;

                $s = new \stdClass();
                $s->index = $anumber;
                $s->indexplusone = $anumber+1;
                $s->sentence = $sentence;
                $s->length = \core_text::strlen($sentence);


                if (!empty($theimageurl)) {
                    $s->imageurl = $theimageurl;
                }
                if (!empty($theaudiourl)) {
                    $s->audiourl = $theaudiourl;
                }
                $testitem->sentences[] = $s;
            }
        }

        // WordShuffle also has a confirm choice option we need to include.
        $testitem->confirmchoice = $itemrecord->{constants::CONFIRMCHOICE};

        return $testitem;
    }

    public static function validate_import($newrecord, $cm)
    {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

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
    public static function get_keycolumns()
    {
        //get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['text5'] = ['jsonname' => 'promptvoice', 'type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYVOICE];
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt', 'type' => 'voiceopts', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYOPTION];
        $keycols['int3'] = ['jsonname' => 'confirmchoice', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::CONFIRMCHOICE];
        $keycols['int2'] = ['jsonname' => 'listenorread', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::LISTENORREAD]; //not boolean ..
        $keycols['text1'] = ['jsonname' => 'sentences', 'type' => 'stringarray', 'optional' => false, 'default' => [], 'dbname' => 'customtext1'];
        $keycols['fileanswer_audio'] = ['jsonname' => constants::FILEANSWER.'1_audio', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        $keycols['fileanswer_image'] = ['jsonname' => constants::FILEANSWER.'1_image', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
 
        return $keycols;
    }

    /*
     * This function return the prompt that the generate method requires for multichoice.
     */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod)
    {
        switch ($generatemethod) {

            case 'extract':
                $prompt = "Extract a one dimensional array of 4 short sentences (sentences) in {language} suitable for {level} level learners from the following passage: [{text}] ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Create a one dimensional array of 4 sentences (sentences), of no more than 7 words per sentence, in {language} suitable for {level} level learners  on the topic of: [{topic}] ";
                break;
        }
        return $prompt;
    }
}
