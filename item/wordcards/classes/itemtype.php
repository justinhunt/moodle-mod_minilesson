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

namespace minilessonitem_wordcards;

use mod_minilesson\local\itemtype\item;
use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a wordcards item in a minilesson activity.
 *
 * @package    minilessonitem_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_VOCABULARY, constants::SKILL_LISTENING, constants::SKILL_WRITING];

    /** The practice type (one of the MODE_ constants below). */
    public const ACTIVITYTYPE = 'customint1';

    /** Hear the term, type it on the onscreen keyboard. */
    public const MODE_LISTENTYPE = 0;
    /** Hear the term, choose it from a set of terms. */
    public const MODE_LISTENCHOOSE = 1;
    /** See the definition, type the term on the onscreen keyboard. */
    public const MODE_TYPEWORD = 2;
    /** See the definition, choose the term from a set of terms. */
    public const MODE_CHOOSEWORD = 3;

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

        // Words.
        $lines = [];
        if (isset($testitem->customtext1)) {
            $lines = explode(PHP_EOL, $testitem->customtext1);
        }
        $testitem->words = $this->process_word_lines($lines);

        // Shuffle so the word order is not predictable (as scatter does).
        // The media urls are already bound to each word object, so they travel with it.
        // We only renumber index/indexplusone so the DOM order stays in sync with the JS pointer.
        shuffle($testitem->words);
        foreach ($testitem->words as $newindex => $word) {
            $word->index = $newindex;
            $word->indexplusone = $newindex + 1;
        }

        // Practice type flags for the template and JS.
        $activitytype = (int)($this->itemrecord->{self::ACTIVITYTYPE} ?? self::MODE_LISTENTYPE);
        $testitem->activitytype = $activitytype;
        $testitem->islistenmode = in_array($activitytype, [self::MODE_LISTENTYPE, self::MODE_LISTENCHOOSE]);
        $testitem->isdefmode = in_array($activitytype, [self::MODE_TYPEWORD, self::MODE_CHOOSEWORD]);
        $testitem->istypemode = in_array($activitytype, [self::MODE_LISTENTYPE, self::MODE_TYPEWORD]);
        $testitem->ischoosemode = in_array($activitytype, [self::MODE_LISTENCHOOSE, self::MODE_CHOOSEWORD]);

        $testitem->hidestartpage = $this->itemrecord->{constants::GAPFILLHIDESTARTPAGE} == 1;

        return $testitem;
    }

    /**
     * Turns the raw "term|definition" lines into word objects for the template/JS.
     *
     * Uploaded media is matched to words by line number: files named 1.mp3 / 2.png etc.
     * bind to the word on that (1-based) line. Words with no uploaded audio fall back
     * to a Polly TTS URL using the item's voice settings.
     *
     * @param array $lines raw lines from customtext1
     * @return array of word objects {index, indexplusone, term, definition, audiourl, imageurl}
     */
    protected function process_word_lines($lines) {
        $customaudio = $this->fetch_sentence_media('audio', 1);
        $customimages = $this->fetch_sentence_media('image', 1);

        $words = [];
        $index = 0;
        foreach ($lines as $line) {
            $line = utils::super_trim($line);
            if (empty($line)) {
                continue;
            }
            $parts = explode('|', $line, 2);
            $term = utils::super_trim($parts[0]);
            $definition = count($parts) > 1 ? utils::super_trim($parts[1]) : '';
            if (empty($term)) {
                continue;
            }

            $word = new stdClass();
            $word->index = $index;
            $word->indexplusone = $index + 1;
            $word->term = $term;
            $word->definition = $definition;

            // Uploaded audio wins, otherwise we use Polly TTS.
            if (isset($customaudio[$word->indexplusone])) {
                $word->audiourl = $customaudio[$word->indexplusone];
            } else {
                $word->audiourl = utils::fetch_polly_url(
                    $this->token,
                    $this->region,
                    $term,
                    $this->itemrecord->{constants::POLLYOPTION},
                    $this->itemrecord->{constants::POLLYVOICE},
                    $this->moduleinstance->id
                );
            }
            $word->imageurl = $customimages[$word->indexplusone] ?? false;

            $index++;
            $words[] = $word;
        }
        return $words;
    }

    /**
     * Validates an imported item record before it is saved.
     *
     * @param stdClass $newrecord the record built from the import data
     * @param stdClass $cm the course module
     * @return stdClass|false an error object {col, message}, or false if there is no error
     */
    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if ($newrecord->customtext1 == '') {
            $error->col = 'customtext1';
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        $lines = array_filter(array_map([utils::class, 'super_trim'], explode(PHP_EOL, $newrecord->customtext1)));
        if (count($lines) < 2) {
            $error->col = 'customtext1';
            $error->message = get_string('error:atleasttwowords', 'minilessonitem_wordcards');
            return $error;
        }
        foreach (array_values($lines) as $i => $line) {
            $parts = explode('|', $line, 2);
            if (utils::super_trim($parts[0]) === '') {
                $error->col = 'customtext1';
                $error->message = get_string('error:badwordline', 'minilessonitem_wordcards', $i + 1);
                return $error;
            }
        }

        // Return false to indicate no error.
        return false;
    }

    /**
     * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
     *
     * @return array the key column definitions
     */
    public static function get_keycolumns() {
        // Get the basic key columns and customize a little for instances of this item type.
        $keycols = parent::get_keycolumns();
        $keycols['int1'] = ['jsonname' => 'activitytype', 'type' => 'int', 'optional' => true,
            'default' => 0, 'dbname' => self::ACTIVITYTYPE];
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt', 'type' => 'voiceopts', 'optional' => true,
            'default' => null, 'dbname' => constants::POLLYOPTION];
        $keycols['text5'] = ['jsonname' => 'promptvoice', 'type' => 'voice', 'optional' => true,
            'default' => null, 'dbname' => constants::POLLYVOICE];
        $keycols['int5'] = ['jsonname' => 'hidestartpage', 'type' => 'boolean', 'optional' => true,
            'default' => 0, 'dbname' => constants::GAPFILLHIDESTARTPAGE];
        $keycols['text1'] = ['jsonname' => 'words', 'type' => 'stringarray', 'optional' => true,
            'default' => [], 'dbname' => 'customtext1'];
        $keycols['fileanswer_audio'] = ['jsonname' => constants::FILEANSWER . '1_audio', 'type' => 'anonymousfile',
            'optional' => true, 'default' => null, 'dbname' => false];
        $keycols['fileanswer_image'] = ['jsonname' => constants::FILEANSWER . '1_image', 'type' => 'anonymousfile',
            'optional' => true, 'default' => null, 'dbname' => false];
        return $keycols;
    }

    /**
     * Shapes the correct answers shown on the attempt review page.
     *
     * @param stdClass $result the result object to decorate
     * @param stdClass $itemquizdata the exported item data
     * @return void
     */
    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $result->hascorrectanswer = true;
        $result->correctans = array_map(function ($word) {
            $sentence = new stdClass();
            $sentence->sentence = $word->term . ($word->definition !== '' ? ' — ' . $word->definition : '');
            return $sentence;
        }, $itemquizdata->words);
        $result->hasanswerdetails = false;
    }
}
