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

namespace minilessonitem_spacegame;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use stdClass;

/**
 * Renderable class for a page item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_VOCABULARY];


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
        $testitem->allowretry = $this->itemrecord->{constants::SG_ALLOWRETRY};
        $testitem->aliencountmultichoice = $this->itemrecord->{constants::SG_ALIENCOUNT_MULTICHOICE};
        $testitem->aliencountmatching = $this->itemrecord->{constants::SG_ALIENCOUNT_MATCHING};
        $testitem->includematching = $this->itemrecord->{constants::SG_INCLUDEMATCHING};

        $testitem->spacegameitems = [];
        $spacegameitems = explode(PHP_EOL, $testitem->customtext1);
        foreach ($spacegameitems as $spacegameitem) {
            $spacegameitem = explode("|", $spacegameitem);
            $spacegameitemobj = new \stdClass();
            $spacegameitemobj->term = trim($spacegameitem[0]);
            $spacegameitemobj->definition = trim(str_replace("\r", "", $spacegameitem[1]));
            $testitem->spacegameitems[] = json_encode($spacegameitemobj);
        }

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

        // Option value checks: the alien counts come from a 2-8 dropdown.
        $countcols = [constants::SG_ALIENCOUNT_MULTICHOICE, constants::SG_ALIENCOUNT_MATCHING];
        foreach ($countcols as $col) {
            if (isset($newrecord->{$col}) && ((int) $newrecord->{$col} < 2 || (int) $newrecord->{$col} > 8)) {
                $error->col = $col;
                $error->message = get_string(
                    'error:invalidoptionvalue',
                    constants::M_COMPONENT,
                    ['value' => $newrecord->{$col}, 'allowed' => '2-8']
                );
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
        return 'An arcade-style shooting game built from question/answer (or word/translation) pairs: aliens '
            . 'carrying answers descend and the learner shoots the one matching the prompt, with an optional '
            . 'second "Shoot the Pairs" stage where both halves of each pair must be shot in sequence. '
            . 'Use it as a fun, fast-paced review of vocabulary or facts already introduced - like scatter, '
            . 'but competitive and time-pressured rather than reflective.';
    }

    /**
     * The agent-facing import field spec for spacegame. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'spacegame';

        $ownfields = [
            'sentences' => [
                'required' => true,
                'description' => 'The pairs the game is built from, as an array of strings, one pair per entry '
                    . 'in the format "Question|Answer" - e.g. a question and its answer, or a word and its '
                    . 'translation. Keep both halves short so they fit on the aliens. Around 6 to 10 pairs '
                    . 'works well.',
                'example' => '["What is the capital of France?|Paris", "mother|a female parent"]',
            ],
            'allowretry' => [
                'description' => 'Whether the learner can replay the game.',
                'options' => [
                    ['value' => '1', 'meaning' => 'Allow replays (default)'],
                    ['value' => '0', 'meaning' => 'One game only'],
                ],
            ],
            'alienmccount' => [
                'description' => 'The maximum number of answer aliens on screen at once in the multiple choice '
                    . 'stage (2-8). More aliens make the game harder; if there are more pairs than aliens, '
                    . 'some pairs do not appear.',
                'options' => [
                    ['value' => '2', 'meaning' => '2 aliens (easiest)'],
                    ['value' => '3', 'meaning' => '3 aliens'],
                    ['value' => '4', 'meaning' => '4 aliens'],
                    ['value' => '5', 'meaning' => '5 aliens (default)'],
                    ['value' => '6', 'meaning' => '6 aliens'],
                    ['value' => '7', 'meaning' => '7 aliens'],
                    ['value' => '8', 'meaning' => '8 aliens (hardest)'],
                ],
            ],
            'includematching' => [
                'description' => 'Whether the second "Shoot the Pairs" stage is included, where both halves of '
                    . 'each pair appear as separate aliens to be shot in sequence.',
                'options' => [
                    ['value' => '1', 'meaning' => 'Include the Shoot the Pairs stage (default)'],
                    ['value' => '0', 'meaning' => 'Multiple choice stage only'],
                ],
            ],
            'alienpaircount' => [
                'description' => 'The maximum number of pairs on screen at once in the Shoot the Pairs stage '
                    . '(2-8). Each pair puts two aliens on screen, so keep this lower than alienmccount.',
                'options' => [
                    ['value' => '2', 'meaning' => '2 pairs (easiest)'],
                    ['value' => '3', 'meaning' => '3 pairs (default)'],
                    ['value' => '4', 'meaning' => '4 pairs'],
                    ['value' => '5', 'meaning' => '5 pairs'],
                    ['value' => '6', 'meaning' => '6 pairs'],
                    ['value' => '7', 'meaning' => '7 pairs'],
                    ['value' => '8', 'meaning' => '8 pairs (hardest)'],
                ],
            ],
        ];
        foreach ($ownfields as $jsonname => $overlay) {
            $fields[$jsonname] = static::aigen_seed_field_spec($jsonname, $overlay);
        }

        return [
            'usage' => 'Compose one item object per game. One "Question|Answer" pair per sentences entry, both '
                . 'halves short enough to read at arcade speed - single words or very short phrases work best. '
                . 'Use it after the vocabulary has been introduced by other items (e.g. cards or wordcards).',
            'fields' => array_values($fields),
            'fileareas' => [],
            'example' => [
                'items' => [
                    [
                        'type' => 'spacegame',
                        'name' => 'Space game: family words',
                        'instructions' => 'Shoot the aliens by selecting the correct answer.',
                        'sentences' => [
                            'family|the people you are related to',
                            'mother|a female parent',
                            'father|a male parent',
                            'brother|a male sibling',
                            'sister|a female sibling',
                        ],
                        'includematching' => 'yes',
                        'alienmccount' => 5,
                        'alienpaircount' => 3,
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
        $keycols['int4'] = ['jsonname' => 'allowretry', 'type' => 'boolean', 'optional' => true, 'default' => 1, 'dbname' => constants::SG_ALLOWRETRY];
        $keycols['int3'] = ['jsonname' => 'includematching', 'type' => 'boolean', 'optional' => true, 'default' => null, 'dbname' => constants::SG_INCLUDEMATCHING];
        $keycols['int1'] = ['jsonname' => 'alienmccount', 'type' => 'int', 'optional' => true, 'default' => 5, 'dbname' => constants::SG_ALIENCOUNT_MULTICHOICE];
        $keycols['int2'] = ['jsonname' => 'alienpaircount', 'type' => 'int', 'optional' => true, 'default' => 3, 'dbname' => constants::SG_ALIENCOUNT_MATCHING];
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
