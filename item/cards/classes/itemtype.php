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

    protected $needsspeechrec = true;

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

        // Correct threshold.
        $testitem->correctthreshold = (int) $this->itemrecord->{constants::FLUENCYCORRECTTHRESHOLD};

        // Cloud Poodll.
        $maxtime = 0;
        $testitem = $this->set_cloudpoodll_details($testitem, $maxtime);
        // In the case of Norwegian, we set the language to Norwegian Bokmal for speech recognition.
        if ($testitem->language == 'no-NO') {
            $testitem->language = 'nb-NO';
        }

        // add a few things to enable the saving of uploaded audio (on S3)
        $testitem->savemedia = 1;
        $testitem->transcode = 1;
        $testitem->expiredays = 365;

        // MS token and region.
        $tokenobject = utils::fetch_msspeech_token($this->moduleinstance->region);
        if ($tokenobject) {
            $testitem->speechtoken = $tokenobject->token;
            $testitem->speechtokenvalidseconds = $tokenobject->validseconds;
            $testitem->speechtokentype = 'msspeech';
        } else {
            $testitem->speechtoken = false;
            $testitem->speechtokenvalidseconds = 0;
            $testitem->speechtokentype = '';
        }

        // We overwrite our regular poodll region with the MS region, eg useast1 becomes eastus, frankfurt becomes westeurope.
        $testitem->region = $tokenobject->region;
        $testitem->speechtokenregion = $tokenobject->region;
        $testitem->savemediaregion = $this->moduleinstance->region;

        // Build sentence objects.
        /* We do this right now so we get character level arrays. So  we can match mspeech per char results
        ultimately we want to do this in a way that suits fluency rather than piggy back on sgapfill. */
        $sentences = [];
        if (isset($testitem->customtext1)) {
            $sentences = explode(PHP_EOL, $testitem->customtext1);
        }

        $testitem->sentences = $this->process_spoken_sentences($sentences, []);
        foreach ($testitem->sentences as $sentence) {
            $sentence->uniqid = uniqid('audio-' . $sentence->index . '-');
            $sentence->ttsautoplay = $sentence->audiourl == $this->nofile ? 0 : 1;
            $sentence->ttsaudiovoice = $testitem->usevoice;
            $sentence->audiosrc = $sentence->audiourl;
            $sentence->audiourl = $sentence->audiourl == $this->nofile ? null : $sentence->audiourl;
            if ($sentence->displayprompt === $sentence->prompt) {
                $sentence->displayprompt = null;
            }
        }
        return $testitem;
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
}
