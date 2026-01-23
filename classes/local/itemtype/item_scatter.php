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

use html_writer;
use mod_minilesson\constants;

/**
 * Renderable class for a scatter item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_scatter extends item
{
    /**
     * The item type constant.
     */
    public const ITEMTYPE = constants::TYPE_SCATTER;

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
        $testitem->allowretry = !empty($this->itemrecord->{constants::SCATTER_ALLOWRETRY});

        $testitem->scatteritems = $testitem->shuffleditems = [];
        $scatteritems = explode(PHP_EOL, $testitem->customtext1);
        foreach ($scatteritems as $i => $scatteritem) {
            $scatteritem = explode("|", $scatteritem);
            $scatteritemobj = new \stdClass();
            $scatteritemobj->term = trim($scatteritem[0]);
            $scatteritemobj->definition = trim(str_replace("\r", "", $scatteritem[1]));
            $testitem->scatteritems[] = $scatteritemobj;
            $testitem->shuffleditems[] = ['key' => $i, 'type' => 'term', 'value' => $scatteritemobj->term, 'htmlid' => html_writer::random_id()];
            $testitem->shuffleditems[] = ['key' => $i, 'type' => 'definition', 'value' => $scatteritemobj->definition, 'htmlid' => html_writer::random_id()];
        }
        shuffle($testitem->shuffleditems);

        return $testitem;
    }

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
        $keycols['text1'] = ['jsonname' => 'sentences', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => 'customtext1'];
        $keycols['int4'] = ['jsonname' => 'allowretry', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::SCATTER_ALLOWRETRY];
        return $keycols;
    }

    /*
  This function return the prompt that the generate method requires.
  */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod)
    {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Select 5 keywords from the following text, and create a 1 dimensional array of 'sentences' of format 'short_keyword_definition|keyword' in {language}: [{text}]. ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Generate a 1 dimensional array of 5 'sentences' of format 'short_keyword_definition|keyword' in {language} from the following keywords: [{keywords}]";
                break;
        }
        return $prompt;
    }
}
