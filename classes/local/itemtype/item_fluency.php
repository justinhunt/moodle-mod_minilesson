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
 * Renderable class for a page item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_fluency extends item {

    //the item type
    public const ITEMTYPE = constants::TYPE_FLUENCY;
    
    public function __construct($itemrecord, $moduleinstance=false, $context = false) {
        parent::__construct($itemrecord, $moduleinstance, $context);
        $this->needs_speechrec = true;
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output) {

        $testitem = new \stdClass();
        $testitem = $this->get_common_elements($testitem);
        $testitem = $this->get_text_answer_elements($testitem);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);

        // Cloud Poodll.
        $maxtime = 0;
        $testitem = $this->set_cloudpoodll_details($testitem, $maxtime);

        // MS token and region.
        $testitem->speechtoken = utils::fetch_msspeech_token($this->moduleinstance->region);
        $testitem->speechtokentype = 'msspeech';
        // We overwrite our regular poodll region with the MS region, eg useast1 becomes eastus, frankfurt becomes westeurope
        $testitem->region = utils::fetch_ms_region($this->moduleinstance->region);

        // Reference text.
        $testitem->referencetext = $testitem->customtext1; //explode(PHP_EOL, $testitem->customtext1)

        return $testitem;
    }

    /*
    * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
    */
    public static function get_keycolumns() {
        // Get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        return $keycols;
    }

}
