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

namespace minilessonitem_multichoice;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a multichoice item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item
{
    public const SHUFFLEANSWER = 'customint5';
    public const CORRECTFEEDBACK = 'customtext6';
    public const HIDEANSWERTEXT = 'customint6';
    public const ANSWERLAYOUT = 'customint7';
    public const ANSWERLAYOUT_DEFAULT = 1;
    public const ANSWERLAYOUT_TWOCOLUMN = 2;

    //the item type
    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output)
    {
        $itemrecord = $this->itemrecord;
        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);
        // Multichoice also needs sentences if we are listening. Its a bit of double up but we do that here.
        $testitem->sentences = [];
        $testitem->imagecontent = false;
        $testitem->audiocontent = false;
        $testitem->hideanswertext = !empty($itemrecord->{itemtype::HIDEANSWERTEXT});
        $testitem->answerlayout = $itemrecord->{itemtype::ANSWERLAYOUT};

        $testitem->layoutclassname = '';
        if ($itemrecord->{constants::LISTENORREAD} != constants::LISTENORREAD_IMAGE && $testitem->answerlayout == itemtype::ANSWERLAYOUT_TWOCOLUMN) {
            $testitem->layoutclassname = "multichoice_twocolumnlayout";
        }

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

        for ($anumber = 1; $anumber <= constants::MAXANSWERS; $anumber++) {
            $theimageurl = '';
            $theaudiourl = '';
            $sentencetext = '';

            // If we have a sentence, we fetch it.
            if (isset($sentences[$anumber - 1]) && !empty(trim($sentences[$anumber - 1]))) {
                $sentencetext = trim($sentences[$anumber - 1]);
            }

            // If we have an image, we fetch it.
            if ($testitem->imagecontent) {
                if (isset($imageurls[$anumber]) && !empty($imageurls[$anumber])) {
                    $theimageurl = $imageurls[$anumber];
                }
            }

            // If we have an audio, we fetch it.
            if ($testitem->audiocontent) {
                if (isset($audiourls[$anumber]) && !empty($audiourls[$anumber])) {
                    $theaudiourl = $audiourls[$anumber];
                } else {
                    // If we have no custom audio then we use the polly audio.
                    if (!empty($sentencetext)) {
                        $theaudiourl = utils::fetch_polly_url(
                            $this->token,
                            $this->region,
                            $sentencetext,
                            $this->itemrecord->{constants::POLLYOPTION},
                            $this->itemrecord->{constants::POLLYVOICE}
                        );
                    }
                }
            }

            // If we have a sentence or an image, we add an answer to the mustache template data.
            if (!empty($sentencetext) || !empty($theimageurl)) {
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
                    if ($itemrecord->{constants::LISTENORREAD} == constants::LISTENORREAD_LISTENANDREAD || $itemrecord->{constants::LISTENORREAD} == constants::LISTENORREAD_IMAGE) {
                        if (!empty($testitem->hideanswertext)) {
                            $s->prompt = '';
                        }
                    }
                }
                if (!empty($theimageurl)) {
                    $s->imageurl = $theimageurl;
                }
                if (!empty($theaudiourl)) {
                    $s->audiourl = $theaudiourl;
                }
                $testitem->sentences[] = $s;
            }
        }

        // Question Point
        // Rich text feedback explaining the correct answer.
        $testitem->correctfeedback = $itemrecord->{itemtype::CORRECTFEEDBACK};

        // Multichoice also has a confirm choice option we need to include.
        $testitem->confirmchoice = $itemrecord->{constants::CONFIRMCHOICE};
        $testitem->shuffleanswers = !empty($itemrecord->{itemtype::SHUFFLEANSWER});
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
        $keycols['text1'] = ['jsonname' => 'answers', 'type' => 'stringarray', 'optional' => false, 'default' => [], 'dbname' => 'customtext1'];
        $keycols['text6'] = ['jsonname' => 'correctfeedback', 'type' => 'string', 'optional' => true, 'default' => '', 'dbname' => itemtype::CORRECTFEEDBACK];
        $keycols['fileanswer_audio'] = ['jsonname' => constants::FILEANSWER . '1_audio', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        $keycols['fileanswer_image'] = ['jsonname' => constants::FILEANSWER . '1_image', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        $keycols['int5'] = ['jsonname' => 'shuffleanswer', 'type' => 'int', 'optional' => true, 'default' => null, 'dbname' => itemtype::SHUFFLEANSWER];
        $keycols['int6'] = ['jsonname' => 'hideanswertext', 'type' => 'int', 'optional' => true, 'default' => null, 'dbname' => itemtype::HIDEANSWERTEXT];
        $keycols['int7'] = ['jsonname' => 'answerlayout', 'type' => 'int', 'optional' => true, 'default' => itemtype::ANSWERLAYOUT_DEFAULT, 'dbname' => itemtype::HIDEANSWERTEXT];
        return $keycols;
    }

    /*
     * This function return the prompt that the generate method requires for multichoice.
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
            // The original multichoice stored each answer in a separate field.
            // We need to convert that to the new format which is a single field with answers separated
            // by a newline character. And we need to handle any images that were uploaded
            // as separate files in the file area to a single file area and named as 1.jpg, 2.jpg, etc.
            $sentences = [];
            $imagecontent = $this->itemrecord->{constants::LISTENORREAD} == constants::LISTENORREAD_IMAGE;
            $sentencefieldindex = 1;
            $mediatype = "image";

            // We do a quick check to see if this item has already been upgraded, in which case we will skip the upgrade.
            if (
                !isset($this->itemrecord->{constants::TEXTANSWER . 2}) ||
                empty(utils::super_trim($this->itemrecord->{constants::TEXTANSWER . 2}))
            ) {
                // This item has already been upgraded, we can skip the upgrade.
                return $success;
            }

            for ($anumber = 1; $anumber <= constants::MAXANSWERS; $anumber++) {
                // If we have a sentence, we fetch it, and then clear the field.
                if (!empty(utils::super_trim($this->itemrecord->{constants::TEXTANSWER . $anumber}))) {
                    $sentences[] = utils::super_trim($this->itemrecord->{constants::TEXTANSWER . $anumber});
                    $this->itemrecord->{constants::TEXTANSWER . $anumber} = '';
                }

                if ($imagecontent) {
                    $fs = get_file_storage();
                    $files = $fs->get_area_files($this->context->id, constants::M_COMPONENT, constants::FILEANSWER . $anumber, $this->itemrecord->id);

                    foreach ($files as $file) {
                        $filename = $file->get_filename();
                        if ($filename == '.') {
                            continue;
                        }
                        $filerecord = new \stdClass();
                        $filerecord->filearea = constants::FILEANSWER . $sentencefieldindex . '_' . $mediatype;
                        // Replace filename with number, eg banana.jpg becomes 1.jpg.
                        $filerecord->filename = $anumber . '.' . pathinfo($file->get_filename(), PATHINFO_EXTENSION);
                        $fs->create_file_from_storedfile($filerecord, $file);
                        // Now we can delete the old file.
                        $fs->delete_area_files($this->context->id, constants::M_COMPONENT, constants::FILEANSWER . $anumber, $this->itemrecord->id);
                        // We only want the first file so we break out of the loop.
                        break;
                    }
                }
            }
            $allsentences = implode(PHP_EOL, $sentences);
            $this->itemrecord->{constants::TEXTANSWER . 1} = $allsentences;
            $success = $DB->update_record(constants::M_QTABLE, $this->itemrecord);
        }

        return $success;
    }

    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $result->hascorrectanswer = true;
        $result->hasincorrectanswer = true;
        if (!empty($itemquizdata->correctfeedback)) {
            $result->hasanswerdetails = true;
            $result->resultsdatajson = json_encode(['correctfeedback' => $itemquizdata->correctfeedback]);
            $result->resultstemplate = self::get_component() . '/multichoiceresults';
        } else {
            $result->hasanswerdetails = false;
        }
        $correctanswers = [];
        $incorrectanswers = [];
        $correctindex = $itemquizdata->correctanswer;

        foreach ($itemquizdata->sentences as $sentance) {
            if ($correctindex == $sentance->indexplusone) {
                $correctanswers[] = $sentance->sentence;
            } else {
                $incorrectanswers[] = $sentance->sentence;
            }
        }

        if (count($correctanswers) == 0) {
            $result->hascorrectanswer = false;
        }
        if (count($incorrectanswers) == 0) {
            $result->hasincorrectanswer = false;
        }

        $result->correctans = ['sentence' => join(' ', $correctanswers)];
        $result->incorrectans = ['sentence' => join('<br> ', $incorrectanswers)];
    }

}
