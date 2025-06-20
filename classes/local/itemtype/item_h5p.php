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

use core_h5p\player;
use mod_minilesson\constants;
use mod_minilesson\utils;
use templatable;
use renderable;

/**
 * Renderable class for a h5p item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_h5p extends item {

    // the item type
    public const ITEMTYPE = constants::TYPE_H5P;

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        $itemrecord = $this->itemrecord;
        $testitem = new \stdClass();
        $testitem = $this->get_common_elements($testitem);
        $testitem = $this->get_text_answer_elements($testitem);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);

        // Get the H5P File
         $mediaurls = $this->fetch_media_urls(constants::H5PFILE, $itemrecord);
        if ($mediaurls && count($mediaurls) > 0) {
            $config = (object) array_fill_keys(['frame', 'export', 'embed', 'copyright'], 0);
            $h5purl = $mediaurls[0];
            $testitem->h5purl = $h5purl;
            $testitem->h5pembedcode = player::display($h5purl, $config, true, 'mod_minilesson');
        } else {
            $testitem->h5purl = false;
        }

        // Max Score
        $testitem->totalmarks = $itemrecord->{constants::TOTALMARKS};

        return $testitem;
    }

    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        // return false to indicate no error
        return false;
    }

    /*
    * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
    */
    public static function get_keycolumns() {
        // get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['text5'] = ['jsonname' => 'promptvoice', 'type' => 'voice', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYVOICE];
        $keycols['int4'] = ['jsonname' => 'promptvoiceopt', 'type' => 'voiceopts', 'optional' => true, 'default' => null, 'dbname' => constants::POLLYOPTION];
        $keycols['int3'] = ['jsonname' => 'confirmchoice', 'type' => 'boolean', 'optional' => true, 'default' => 0, 'dbname' => constants::CONFIRMCHOICE];
        $keycols['int2'] = ['jsonname' => 'listenorread', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::LISTENORREAD]; // not boolean ..
        return $keycols;
    }

}
