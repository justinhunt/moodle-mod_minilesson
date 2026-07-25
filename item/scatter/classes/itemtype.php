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

namespace minilessonitem_scatter;

use mod_minilesson\local\itemtype\item;

use html_writer;
use mod_minilesson\constants;
use stdClass;

/**
 * Renderable class for a scatter item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_VOCABULARY];

    /**
     * This item type has a splash screen, drawn as a centred white card.
     *
     * @return bool
     */
    public function uses_boxed_layout() {
        return true;
    }

    public const ALLOWRETRY = 'customint4';


    /**
     * The item type constant.
     */
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
        $testitem->allowretry = !empty($this->itemrecord->{self::ALLOWRETRY});
        $testitem->hintrtl = $this->itemrecord->{constants::SCATTERHINTRTL} == 1;

        $testitem->scatteritems = $testitem->shuffleditems = [];
        $scatteritems = explode(PHP_EOL, $testitem->customtext1);
        foreach ($scatteritems as $i => $scatteritem) {
            $scatteritem = explode("|", $scatteritem);
            $scatteritemobj = new \stdClass();
            $scatteritemobj->term = trim($scatteritem[0]);
            $scatteritemobj->definition = trim(str_replace("\r", "", $scatteritem[1]));
            $testitem->scatteritems[] = $scatteritemobj;
            $testitem->shuffleditems[] = ['key' => $i,
                'type' => 'term',
                'rtl' => !empty($testitem->rtl) ? 'rtl' : '',
                'value' => $scatteritemobj->term,
                'htmlid' => html_writer::random_id()];

            $testitem->shuffleditems[] = ['key' => $i,
                'type' => 'definition',
                'rtl' => $testitem->hintrtl ? 'rtl' : '',
                'value' => $scatteritemobj->definition,
                'htmlid' => html_writer::random_id()];
        }
        shuffle($testitem->shuffleditems);

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

        $pairs = array_filter(array_map('trim', explode(PHP_EOL, (string) $newrecord->customtext1)), function ($pair) {
            return $pair !== '';
        });
        if (count($pairs) == 0) {
            $error->col = 'customtext1';
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }
        foreach ($pairs as $pair) {
            if (strpos($pair, '|') === false) {
                $error->col = 'customtext1';
                $error->message = get_string('error:needspipepair', constants::M_COMPONENT);
                return $error;
            }
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
        return 'A matching game: a grid of cards built from a set of term/match pairs. The learner clears the '
            . 'grid by tapping matching pairs. Use it for vocabulary review - matching words to translations, '
            . 'definitions, synonyms or collocations. It works best as a fun review of language already introduced.';
    }

    /**
     * The agent-facing import field spec for scatter. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'scatter';
        $fields['timelimit']['description'] = 'Time limit for clearing the grid, in seconds. 0 = no time limit. '
            . 'A time limit (e.g. 60) makes the game more exciting.';

        $ownfields = [
            'sentences' => [
                'required' => true,
                'description' => 'The pairs to match, as an array of strings, one pair per entry in the format '
                    . '"Term|Match". The match can be a translation, definition or synonym. Around 5 to 10 pairs '
                    . 'works well; each pair becomes two cards in the grid.',
                'example' => '["airport|a place where planes take off and land", "deadline|the time by which something must be done"]',
            ],
            'hintrtl' => [
                'description' => 'Display the match/definition side in right-to-left format (for matches written '
                    . 'in an RTL language such as Arabic or Hebrew).',
                'options' => [
                    ['value' => '0', 'meaning' => 'Left-to-right (default)'],
                    ['value' => '1', 'meaning' => 'Right-to-left'],
                ],
            ],
            'allowretry' => [
                'description' => 'Whether the learner can retry the activity if they do not clear the grid.',
                'options' => [
                    ['value' => '0', 'meaning' => 'One attempt (default)'],
                    ['value' => '1', 'meaning' => 'Allow retries'],
                ],
            ],
        ];
        foreach ($ownfields as $jsonname => $overlay) {
            $fields[$jsonname] = static::aigen_seed_field_spec($jsonname, $overlay);
        }

        return [
            'usage' => 'Compose one item object per matching game. One "Term|Match" pair per sentences entry; '
                . 'terms in the lesson language, matches as translations (e.g. in the learner\'s native language) '
                . 'or simple definitions. Keep terms and matches short so they fit on the cards.',
            'fields' => array_values($fields),
            'fileareas' => [],
            'example' => [
                'items' => [
                    [
                        'type' => 'scatter',
                        'name' => 'Match the word pairs',
                        'instructions' => 'Match the word pairs',
                        'sentences' => [
                            'airport|a place where planes take off and land',
                            'brochure|a small booklet with information',
                            'deadline|the time by which something must be done',
                            'invoice|a bill for goods or services',
                        ],
                        'timelimit' => 60,
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
        $keycols['text1'] = ['jsonname' => 'sentences', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => 'customtext1'];
        $keycols['int6'] = ['jsonname' => 'hintrtl', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::SCATTERHINTRTL];
        $keycols['int4'] = ['jsonname' => 'allowretry', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => self::ALLOWRETRY];
        return $keycols;
    }

    /*
    This function return the prompt that the generate method requires.
    */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Select 5 keywords from the following text, and create a 1 dimensional array of 'sentences' of format 'keyword|keyword translation': [{text}]. " . PHP_EOL;
                $prompt .= "The translations should be in {native_language}." . PHP_EOL;
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Generate a 1 dimensional array of 'sentences' of format 'keyword|keyword translation' from the following keywords: [{keywords}]" . PHP_EOL;
                $prompt .= "The translations should be in {native_language}." . PHP_EOL;
                break;
        }
        return $prompt;
    }

}
