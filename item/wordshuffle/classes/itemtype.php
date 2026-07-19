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

namespace minilessonitem_wordshuffle;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Renderable class for a wordshuffle item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_GRAMMAR];


    // The item type.
    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        $itemrecord = $this->itemrecord;
        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);
        // Do we need audio
        $testitem->readsentence = !empty($itemrecord->{constants::READSENTENCE});

        // Prepare data arrays.
        $testitem->sentences = [];
        $testitem->imagecontent = true;
        $testitem->audiocontent = $testitem->readsentence;

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
                // If there is a pipe (for a hint), only use the part before the pipe
                $sentenceparts = explode('|', $sentencetext, 2);
                $sentenceclean = str_replace(['[', ']'], ['', ''], $sentenceparts[0]);
            }

            // If we have an image, we fetch it.
            if ($testitem->imagecontent) {
                if (isset($imageurls[$anumber + 1]) && !empty($imageurls[$anumber + 1])) {
                    $theimageurl = $imageurls[$anumber + 1];
                }
            }

            // If we have an audio, we fetch it.
            if ($testitem->audiocontent) {
                if (isset($audiourls[$anumber + 1]) && !empty($audiourls[$anumber + 1])) {
                    $theaudiourl = $audiourls[$anumber + 1];
                } else {
                    // If we have no custom audio then we use the polly audio.
                    if (!empty($sentencetext)) {
                        $theaudiourl = utils::fetch_polly_url(
                            $this->token,
                            $this->region,
                            $sentenceclean,
                            $this->itemrecord->{constants::POLLYOPTION},
                            $this->itemrecord->{constants::POLLYVOICE},
                            $this->moduleinstance->id
                        );
                    }
                }
            }

            // If we have a sentence or an image, we add an answer to the mustache template data.
            if (!empty($sentencetext)) {
                $sentence = $sentencetext;

                $s = new \stdClass();
                $s->index = $anumber;
                $s->indexplusone = $anumber + 1;
                $s->sentence = $sentence;
                $s->sentenceclean = $sentenceclean;
                $s->length = \core_text::strlen($sentence);
                $s->imageurl = false;
                $s->audiourl = false;

                if (!empty($theimageurl)) {
                    $s->imageurl = $theimageurl;
                }
                if (!empty($theaudiourl)) {
                    $s->audiourl = $theaudiourl;
                }
                $testitem->sentences[] = $s;
            }
        }

        $processedsentences = $this->parse_gapfill_sentences($sentences, true);
        foreach ($processedsentences as $processedsentence) {
            if (isset($testitem->sentences[$processedsentence->index])) {
                $testitem->sentences[$processedsentence->index]->gapwords = $processedsentence->gapwords;
                $testitem->sentences[$processedsentence->index]->hint = $processedsentence->definition;
                $gaps = array_filter(
                    $processedsentence->gapwords,
                    function ($gap) {
                        return !empty($gap['isgap']);
                    }
                );
                $gapsanddistractors = $gaps;
                foreach ($processedsentence->extrawords as $extraword) {
                    if (!empty($extraword)) {
                        $gapsanddistractors[] = ['word' => $extraword, 'isgap' => true, 'gapindex' => 9999];
                    }
                }
                shuffle($gapsanddistractors);
                $testitem->sentences[$processedsentence->index]->randomgaps = $gapsanddistractors;
            }
        }

        // If shuffle order is on, randomize the order the sentence sets are delivered in.
        // The image/audio/gapwords are already bound to each sentence object, so they travel with it.
        // We only need to renumber index/indexplusone so the DOM wordset order stays in sync with the JS pointer.
        if (!empty($itemrecord->{constants::WORDSHUFFLESHUFFLEORDER})) {
            shuffle($testitem->sentences);
            foreach ($testitem->sentences as $newindex => $thesentence) {
                $thesentence->index = $newindex;
                $thesentence->indexplusone = $newindex + 1;
            }
        }

        // WordShuffle also has hide startpage and allow retry
        $testitem->hidestartpage = $itemrecord->{constants::WORDSHUFFLEHIDESTARTPAGE} == 1;
        $testitem->allowretry = $itemrecord->{constants::GAPFILLALLOWRETRY} == 1;
        $testitem->hintrtl = $itemrecord->{constants::WORDSHUFFLEHINTRTL} == 1;
        $testitem->newui = true;
        return $testitem;
    }

    /**
     * Validates an import record for this item type.
     *
     * @param \stdClass $newrecord the db-ready import record
     * @param \stdClass $cm the course module
     * @return false|\stdClass false when valid, or an error object with col and message
     */
    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        $sentences = array_filter(array_map('trim', explode(PHP_EOL, (string) $newrecord->customtext1)), function ($sentence) {
            return $sentence !== '';
        });
        if (count($sentences) == 0) {
            $error->col = 'customtext1';
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }
        if (!preg_match('/\[[^\]]+\]/', (string) $newrecord->customtext1)) {
            $error->col = 'customtext1';
            $error->message = get_string('error:nogaps', constants::M_COMPONENT);
            return $error;
        }

        // return false to indicate no error
        return false;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'A set of sentences with the bracketed words removed and shown as shuffled tiles; the learner '
            . 'taps the tiles in the right order to rebuild each sentence. With several bracketed words it is a '
            . 'word-ordering exercise; with one bracketed word plus distractors it becomes a choose-the-correct-word '
            . 'exercise. Use it for grammar (word order, verb forms) and vocabulary-in-context practice.';
    }

    /**
     * The agent-facing import field spec for wordshuffle. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'wordshuffle';

        $ownfields = [
            'sentences' => [
                'required' => true,
                'description' => 'The sentences, as an array of strings, one per entry in the format '
                    . '"Sentence with [word1] and [word2].|hint|distractor1,distractor2". The bracketed words '
                    . 'become shuffled tiles the learner must place; a bracket can hold a multi-word phrase '
                    . '(e.g. "[get up]"). The hint (optional, e.g. a translation) is shown with the sentence. '
                    . 'The distractors (optional, comma or space separated) add extra wrong tiles - combine one '
                    . 'bracketed word with distractors for a choose-the-correct-word exercise.',
                'example' => '["I [get up] at seven o\'clock.|What I do first each day.|have breakfast,go to work"]',
            ],
            'readsentence' => [
                'description' => 'Whether each sentence is read aloud by TTS (using promptvoice) - making the activity a form of dictation.',
                'options' => [
                    ['value' => '0', 'meaning' => 'No TTS audio (default)'],
                    ['value' => '1', 'meaning' => 'Read each sentence aloud'],
                ],
            ],
            'promptvoice' => [
                'description' => 'The TTS voice that reads the sentences aloud (used with readsentence=1). '
                    . 'A voice display name (case-insensitive), e.g. "Joey" (en-US) or "Mathieu" (fr-FR), '
                    . 'or "auto" to let the server pick a voice matching the lesson language.',
                'example' => 'auto',
            ],
            'promptvoiceopt' => [
                'description' => 'Reading speed / processing option for the sentence TTS audio.',
                'options' => [
                    ['value' => 'normal', 'meaning' => 'Normal speed (default; any unrecognised value also maps to normal)'],
                    ['value' => 'slow', 'meaning' => 'Slow reading speed'],
                    ['value' => 'veryslow', 'meaning' => 'Very slow reading speed'],
                ],
            ],
            'allowretry' => [
                'description' => 'Whether the learner can submit new attempts when their response was not correct.',
                'options' => [
                    ['value' => '0', 'meaning' => 'One attempt (default)'],
                    ['value' => '1', 'meaning' => 'Allow retries'],
                ],
            ],
            'shuffleorder' => [
                'description' => 'Whether the sentence order is randomized for each learner. Each sentence keeps '
                    . 'its matching image and audio.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Keep the authored order (default)'],
                    ['value' => '1', 'meaning' => 'Shuffle the sentence order'],
                ],
            ],
            'hidestartpage' => [
                'description' => 'Whether the activity begins as soon as it has loaded, instead of showing a '
                    . 'start/splash page first.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Show the start page (default)'],
                    ['value' => '1', 'meaning' => 'Start immediately'],
                ],
            ],
            'hintrtl' => [
                'description' => 'Display the hints in right-to-left format (for hints written in an RTL language '
                    . 'such as Arabic or Hebrew).',
                'options' => [
                    ['value' => '0', 'meaning' => 'Left-to-right hints (default)'],
                    ['value' => '1', 'meaning' => 'Right-to-left hints'],
                ],
            ],
        ];
        foreach ($ownfields as $jsonname => $overlay) {
            $fields[$jsonname] = static::aigen_seed_field_spec($jsonname, $overlay);
        }

        $fields['filesid'] = [
            'jsonname' => 'filesid',
            'type' => 'int',
            'required' => false,
            'default' => '',
            'description' => 'Links this item to its entry in the top level "files" object of the payload. '
                . 'Only needed when the sentences have uploaded audio or images.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per set of sentences (around 5 to 10 works well). '
                . 'Enclose the words to shuffle in square brackets. For word-order practice bracket several words '
                . 'or phrases per sentence; for choose-the-correct-word practice bracket one word and add '
                . 'distractors after the second pipe. Hints in the learner\'s native language work well.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::FILEANSWER . '1_audio',
                    'description' => 'Uploaded audio for the sentences (overrides the readsentence TTS audio). '
                        . 'Usually unnecessary: prefer TTS via readsentence and promptvoice.',
                    'filenames' => 'Name each file for its 1-based sentence line number: "1.mp3", "2.mp3", ...',
                ],
                [
                    'filearea' => constants::FILEANSWER . '1_image',
                    'description' => 'Optional image shown alongside each sentence.',
                    'filenames' => 'Name each file for its 1-based sentence line number: '
                        . '"1.png", "2.png", ... (.jpg is also fine).',
                ],
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'wordshuffle',
                        'name' => 'Word shuffle',
                        'instructions' => 'Put the words in the correct order to form a sentence.',
                        'sentences' => [
                            'I [get up] at seven o\'clock.|What I do first each day.|have breakfast,go to work',
                            'I [have breakfast] with my family.|Our morning meal together.|get up,go home',
                            'I [go to bed] at eleven.|The end of my day.|start work,have lunch',
                        ],
                        'readsentence' => 1,
                        'promptvoice' => 'auto',
                        'allowretry' => 'yes',
                    ],
                ],
            ],
        ];
    }

    /*
     * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
     */
    public static function get_keycolumns() {
        // get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['text5'] = ['jsonname' => 'promptvoice', 'type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYVOICE];
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt', 'type' => 'voiceopts', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYOPTION];
        $keycols['int3'] = ['jsonname' => 'allowretry', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::GAPFILLALLOWRETRY];
        $keycols['int2'] = ['jsonname' => 'readsentence', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::READSENTENCE]; // not boolean ..
        $keycols['text1'] = ['jsonname' => 'sentences', 'type' => 'stringarray', 'optional' => false, 'default' => [], 'dbname' => 'customtext1'];
        $keycols['int5'] = ['jsonname' => 'hidestartpage', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::WORDSHUFFLEHIDESTARTPAGE];
        $keycols['int6'] = ['jsonname' => 'hintrtl', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::WORDSHUFFLEHINTRTL];
        $keycols['int1'] = ['jsonname' => 'shuffleorder', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::WORDSHUFFLESHUFFLEORDER];
        $keycols['fileanswer_audio'] = ['jsonname' => constants::FILEANSWER . '1_audio', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];
        $keycols['fileanswer_image'] = ['jsonname' => constants::FILEANSWER . '1_image', 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];

        return $keycols;
    }

    /*
     * This function return the prompt that the generate method requires for multichoice.
     */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Extract a one dimensional array of 4 short sentences (sentences) in {language} suitable for {level} level learners from the following passage: [{text}] " . PHP_EOL;
                $prompt .= "In each sentence surround all but the first two words with square brackets, e.g [word]. " . PHP_EOL;
                $prompt .= "Create a second array (data2) using the same sentences but without the square brackets." . PHP_EOL;
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Create a one dimensional array of 4 sentences (sentences), of between 4 and 8 words per sentence, in {language} suitable for {level} level learners  on the topic of: [{topic}] " . PHP_EOL;
                $prompt .= "In each sentence select three  words, and surround them each with square brackets. One of the words selected must be a keyword. e.g The [purple] [people] [eater] is a song." . PHP_EOL;
                $prompt .= "Create a second array (data2) using the same sentences but without the square brackets." . PHP_EOL;
                break;
        }
        return $prompt;
    }

}
