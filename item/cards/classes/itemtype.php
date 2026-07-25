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

    /** @var bool this item type produces no grade/result. */
    public $gradeable = false;

    protected $needsspeechrec = false;

    /**
     * Load the (locally shipped) Swiper CSS in the page head. The carousel markup relies on
     * these rules to clip to a single card, and this item's own stylesheet makes the slide list
     * a flex row from first paint, so fetching the Swiper CSS lazily would leave a window in
     * which every card is laid out side by side. The Swiper library itself is still loaded
     * lazily by this item's JS (amd/src/itemtype.js).
     *
     * @param \moodle_page $page The page to add requirements to.
     * @return void
     */
    #[Override]
    public static function page_requirements(\moodle_page $page) {
        $page->requires->css(new \moodle_url('/mod/minilesson/item/cards/css/swiper-bundle.min.css'));
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
            $sentence->ttsautoplay = empty($sentence->audiourl) ? 0 : 1;
            $sentence->ttsaudiovoice = $testitem->usevoice;
            $sentence->audiosrc = $sentence->audiourl;
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

        // Prepare sentence media. Line 1 audio lives in customfile1_audio, line 3 audio in customfile2_audio.
        $sentenceimages = $this->fetch_sentence_media('image', 1);
        $sentenceaudio = $this->fetch_sentence_media('audio', 1);
        $line3audio = $this->fetch_sentence_media('audio', 2);

        $readsentence = !empty($this->itemrecord->{constants::READSENTENCE});

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
            $cardline4 = "";

            // if we have a pipe prompt = array[0] and response = array[1]
            $cardlines = explode('|', $sentence);
            if (count($cardlines) > 1) {
                $cardline1 = utils::super_trim($cardlines[0]);
                $cardline2 = utils::super_trim($cardlines[1]);
                if (count($cardlines) > 2) {
                    $cardline3 = utils::super_trim($cardlines[2]);
                }
                if (count($cardlines) > 3) {
                    $cardline4 = utils::super_trim($cardlines[3]);
                }
            } else {
                $cardline1 = $sentence;
            }

            // Card (line 1) audio: an uploaded file always wins, otherwise TTS when "read card text" is on.
            if (isset($sentenceaudio[$sentenceindex])) {
                $theaudiourl = $sentenceaudio[$sentenceindex];
            } else if ($readsentence) {
                $theaudiourl = utils::fetch_polly_url(
                    $this->token,
                    $this->region,
                    $cardline1,
                    $this->itemrecord->{constants::POLLYOPTION},
                    $this->itemrecord->{constants::POLLYVOICE},
                    $this->moduleinstance->id
                );
            } else {
                $theaudiourl = false;
            }

            // Line 3 audio: same rule, but it never autoplays (tap the line/speaker icon to play).
            if (isset($line3audio[$sentenceindex])) {
                $line3audiourl = $line3audio[$sentenceindex];
            } else if ($readsentence && $cardline3 !== '') {
                $line3audiourl = utils::fetch_polly_url(
                    $this->token,
                    $this->region,
                    $cardline3,
                    $this->itemrecord->{constants::POLLYOPTION},
                    $this->itemrecord->{constants::POLLYVOICE},
                    $this->moduleinstance->id
                );
            } else {
                $line3audiourl = false;
            }

            // Build the sentence object.
            $s = new \stdClass();
            $s->index = $index;
            $s->indexplusone = $index + 1;
            $s->sentence = $sentence;
            $s->cardline1 = $cardline1;
            $s->cardline2 = $cardline2;
            $s->cardline3 = $cardline3;
            $s->cardline4 = $cardline4;
            $s->length = \core_text::strlen($s->sentence);
            $s->imageurl = isset($sentenceimages[$sentenceindex]) ? $sentenceimages[$sentenceindex] : false;
            $s->audiourl = $theaudiourl;
            $s->line3audiourl = $line3audiourl;

            $index++;
            $sentenceobjects[] = $s;
        }
        return $sentenceobjects;
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

        $cards = array_filter(array_map('trim', explode(PHP_EOL, (string) $newrecord->customtext1)), function ($card) {
            return $card !== '';
        });
        if (count($cards) == 0) {
            $error->col = 'customtext1';
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }
        return false;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'A series of browsable cards, each showing a word or phrase with optional extra text lines '
            . '(translation, model sentence, model sentence translation), an optional picture and optional audio. '
            . 'There is no question and no grade. Use it to introduce new vocabulary or phrases before they are '
            . 'practised by other item types.';
    }

    /**
     * The agent-facing import field spec for cards. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'cards';

        $ownfields = [
            'sentences' => [
                'required' => true,
                'description' => 'The cards, as an array of strings, one card per entry with up to four '
                    . 'pipe-separated text lines: "word|translation|model sentence|model sentence translation". '
                    . 'Only the first line is required. Around 5 to 10 cards works well.',
                'example' => '["airport|Flughafen|The airport is always busy.|Der Flughafen ist immer voll."]',
            ],
            'dictationstyle' => [
                'description' => 'Whether the card word (line 1) and model sentence (line 3) are read aloud '
                    . 'by TTS using the promptvoice.',
                'options' => [
                    ['value' => '0', 'meaning' => 'No TTS audio (default)'],
                    ['value' => '1', 'meaning' => 'Read the word and model sentence aloud'],
                ],
            ],
            'promptvoice' => [
                'description' => 'The TTS voice that reads the card text aloud (used with dictationstyle=1). '
                    . 'A voice display name (case-insensitive), e.g. "Joey" (en-US) or "Mathieu" (fr-FR), '
                    . 'or "auto" to let the server pick a voice matching the lesson language.',
                'example' => 'auto',
            ],
            'promptvoiceopt' => [
                'description' => 'Reading speed / processing option for the card TTS audio.',
                'options' => [
                    ['value' => 'normal', 'meaning' => 'Normal speed (default; any unrecognised value also maps to normal)'],
                    ['value' => 'slow', 'meaning' => 'Slow reading speed'],
                    ['value' => 'veryslow', 'meaning' => 'Very slow reading speed'],
                ],
            ],
            'shuffleorder' => [
                'description' => 'Whether the card order is randomized for each learner. Each card keeps its '
                    . 'matching image and audio.',
                'options' => [
                    ['value' => '0', 'meaning' => 'Keep the authored order (default)'],
                    ['value' => '1', 'meaning' => 'Shuffle the card order'],
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
                . 'Only needed when the cards have images or uploaded audio.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per set of cards. Line 1 is the word or phrase in the lesson '
                . 'language; add a translation, model sentence and model sentence translation as further pipe '
                . 'separated lines where helpful. Set dictationstyle=1 so learners hear the words; card images '
                . 'go in the ' . constants::FILEANSWER . '1_image file area, numbered by card.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::FILEANSWER . '1_image',
                    'description' => 'A picture for each card.',
                    'filenames' => 'Name each file for its 1-based card number: '
                        . '"1.png", "2.png", ... (.jpg is also fine).',
                ],
                [
                    'filearea' => constants::FILEANSWER . '1_audio',
                    'description' => 'Uploaded audio for card text line 1, the word/phrase '
                        . '(overrides the dictationstyle TTS audio). Usually unnecessary: prefer TTS.',
                    'filenames' => 'Name each file for its 1-based card number: "1.mp3", "2.mp3", ...',
                ],
                [
                    'filearea' => constants::FILEANSWER . '2_audio',
                    'description' => 'Uploaded audio for card text line 3, the model sentence '
                        . '(overrides the dictationstyle TTS audio). Usually unnecessary: prefer TTS.',
                    'filenames' => 'Name each file for its 1-based card number: "1.mp3", "2.mp3", ...',
                ],
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'cards',
                        'name' => 'New vocabulary',
                        'instructions' => 'Review the cards on screen. You can use the next and back buttons '
                            . 'to see other cards.',
                        'sentences' => [
                            'airport|空港|The airport in that city is always busy.|その町にある空港はいつも人が多いです。',
                            'brochure|パンフレット|This brochure has information about our company.|このパンフレットには当社の情報が書かれています。',
                            'deadline|締め切り|The deadline for this project is in three hours.|このプロジェクトの締め切りは3時間後です。',
                        ],
                        'dictationstyle' => 1,
                        'promptvoice' => 'auto',
                    ],
                ],
            ],
        ];
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
        $keycolumns['fileanswer2_audio'] = [
            'jsonname' => constants::FILEANSWER . '2_audio',
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
