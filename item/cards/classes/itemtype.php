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

namespace minilessonitem_cards;

use mod_minilesson\constants;
use mod_minilesson\local\itemtype\item;
use mod_minilesson\utils;
use Override;

/**
 * Class itemtype
 *
 * @package    minilessonitem_cards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_VOCABULARY];


    protected $needsspeechrec = false;

    protected $nofile = "nofile";


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

        // Is rtl
        $testitem->rtl = utils::is_rtl($this->language);

        $testitem->readsentence = $this->itemrecord->{constants::READSENTENCE} == 1;

        // Cloud Poodll.
        $maxtime = 0;
        $testitem = $this->set_cloudpoodll_details($testitem, $maxtime);

        // Build sentence objects.
        $sentences = [];
        if (isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->customtext1);
        }

        $testitem->sentences = $this->process_card_lines($sentences, []);

        // If shuffle order is on, randomize the order the cards are delivered in.
        // The image/audio and card text are already bound to each sentence object, so they travel with it.
        // We renumber index/indexplusone before the loop below so the uniqid and DOM order stay in sync.
        if (!empty($this->itemrecord->{constants::CARDSSHUFFLEORDER})) {
            shuffle($testitem->sentences);
            foreach ($testitem->sentences as $newindex => $thesentence) {
                $thesentence->index = $newindex;
                $thesentence->indexplusone = $newindex + 1;
            }
        }

        foreach ($testitem->sentences as $sentence) {
            $sentence->uniqid = uniqid('audio-' . $sentence->index . '-');
            $sentence->ttsautoplay = $sentence->audiourl == $this->nofile ? 0 : 1;
            $sentence->ttsaudiovoice = $testitem->usevoice;
            $sentence->audiosrc = $sentence->audiourl;
            $sentence->audiourl = $sentence->audiourl == $this->nofile ? null : $sentence->audiourl;
        }
        return $testitem;
    }

    /**
     * Process the card lines.
     *
     * @param array $sentences array of sentences.
     * @return array array of sentence objects.
     */
    protected function process_card_lines($sentences) {
        // build a sentences object for mustache and JS
        $index = 0;
        $sentenceobjects = [];

        // Prepare sentence media.
        $sentenceimages = $this->fetch_sentence_media('image', 1);
        $sentenceaudio = $this->fetch_sentence_media('audio', 1);

        $sentenceindex = 0;
        foreach ($sentences as $sentence) {
            $sentence = utils::super_trim($sentence);
            if (empty($sentence)) {
                continue;
            }
            // Sentence index starts at 1 and keys with sentenceaudios and sentenceimages
            $sentenceindex++;

            // Default card lines
            $cardline1 = "";
            $cardline2 = "";
            $cardline3 = "";

            // if we have a pipe prompt = array[0] and response = array[1]
            $cardlines = explode('|', $sentence);
            if (count($cardlines) > 1) {
                $cardline1 = utils::super_trim($cardlines[0]);
                $cardline2 = utils::super_trim($cardlines[1]);
                if (count($cardlines) > 2) {
                    $cardline3 = utils::super_trim($cardlines[2]);
                }
            } else {
                $cardline1 = $sentence;
            }

            // We prepare the audio url.
            if (isset($sentenceaudio[$sentenceindex])) {
                $theaudiourl = $sentenceaudio[$sentenceindex];
            } else {
                // If we have no custom audio then we use the polly audio.
                $theaudiourl = utils::fetch_polly_url(
                    $this->token,
                    $this->region,
                    $cardline1,
                    $this->itemrecord->{constants::POLLYOPTION},
                    $this->itemrecord->{constants::POLLYVOICE},
                    $this->moduleinstance->id
                );
            }

            // Build the sentence object.
            $s = new \stdClass();
            $s->index = $index;
            $s->indexplusone = $index + 1;
            $s->sentence = $sentence;
            $s->cardline1 = $cardline1;
            $s->cardline2 = $cardline2;
            $s->cardline3 = $cardline3;
            $s->length = \core_text::strlen($s->sentence);
            $s->imageurl = isset($sentenceimages[$sentenceindex]) ? $sentenceimages[$sentenceindex] : false;
            $s->audiourl = $theaudiourl;

            $index++;
            $sentenceobjects[] = $s;
        }
        return $sentenceobjects;
    }


    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if ($newrecord->customtext1 == '') {
            $error->col = 'customtext1';
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }
        return false;
    }

    #[Override]
    public static function get_keycolumns() {
        $keycolumns = parent::get_keycolumns();
        $keycolumns['int2'] = [
            'jsonname' => 'dictationstyle',
            'type' => 'boolean',
            'optional' => true,
            'default' => 0,
            'dbname' => constants::READSENTENCE,
        ];
        $keycolumns['int1'] = [
            'jsonname' => 'shuffleorder',
            'type' => 'boolean',
            'optional' => true,
            'default' => 0,
            'dbname' => constants::CARDSSHUFFLEORDER,
        ];
        $keycolumns['int4'] = [
            'jsonname' => 'promptvoiceopt',
            'type' => 'voiceopts',
            'optional' => true,
            'default' => null,
            'dbname' => constants::POLLYOPTION,
        ];
        $keycolumns['text5'] = [
            'jsonname' => 'promptvoice',
            'type' => 'voice',
            'optional' => true,
            'default' => null,
            'dbname' => constants::POLLYVOICE,
        ];
        $keycolumns['text1'] = [
            'jsonname' => 'sentences',
            'type' => 'stringarray',
            'optional' => true,
            'default' => [],
            'dbname' => 'customtext1',
        ];
        $keycolumns['fileanswer_audio'] = [
            'jsonname' => constants::FILEANSWER . '1_audio',
            'type' => 'anonymousfile',
            'optional' => true,
            'default' => null,
            'dbname' => false,
        ];
        $keycolumns['fileanswer_image'] = [
            'jsonname' => constants::FILEANSWER . '1_image',
            'type' => 'anonymousfile',
            'optional' => true,
            'default' => null,
            'dbname' => false,
        ];
        return $keycolumns;
    }

    protected function fetch_sentence_media($mediatype, $index) {
        $media = parent::fetch_sentence_media($mediatype, $index);
        if ($mediatype == 'image') {
            return $media;
        }

        $sentences = [];
        if (isset($this->itemrecord->customtext1)) {
            $sentences = explode(PHP_EOL, $this->itemrecord->customtext1);
        }

        foreach ($sentences as $key => $sentence) {
            $mediacount = $key + 1;
            if (!isset($media[$mediacount]) && empty($this->itemrecord->{constants::READSENTENCE})) {
                $media[$mediacount] = $this->nofile;
            }
        }
        return $media;
    }

      /*
    * This function return the prompt that the generate method requires for creating card items.
    */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Create a one dimensional array of pipe delimited strings (sentences), of the following pattern: keyword|keyword-translation|keyword-examplesentence" . PHP_EOL;
                $prompt .= " The keywords to use should be extracted from the following passage of text: [{textpassage}]. " . PHP_EOL;
                $prompt .= " The translation language is: {nativelanguage}. The keyword and example sentence language is: {targetlanguage}" . PHP_EOL;
                $prompt .= " Also create a matching one dimensional array of image generation prompts to illustrate the keyword's in the same sense as it is used in the example sentence. The images should be of style: {imagestyle}. " . PHP_EOL;
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Create a one dimensional array of pipe delimited strings (sentences), of the following pattern: keyword|keyword-translation|keyword-examplesentence" . PHP_EOL;
                $prompt .= " The keywords to use are contained in this list: [{keywords}]. " . PHP_EOL;
                $prompt .= " The translation language is: {nativelanguage}. The keyword and example sentence language is: {targetlanguage}" . PHP_EOL;
                $prompt .= " Also create a matching one dimensional array of image generation prompts to illustrate the keyword's in the same sense as it is used in the example sentence. The images should be of style: {imagestyle}. " . PHP_EOL;
                break;
        }
        return $prompt;
    }

}
