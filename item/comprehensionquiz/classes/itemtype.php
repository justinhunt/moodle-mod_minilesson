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

namespace minilessonitem_comprehensionquiz;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use stdClass;

/**
 * Renderable class for a comprehension quiz item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item
{
    //the item type
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

        return $testitem;
    }

    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $result->hascorrectanswer = true;
        $result->hasincorrectanswer = true;
        if (!empty($itemquizdata->correctfeedback)) {
            $result->hasanswerdetails = true;
            $result->resultsdatajson = json_encode(['correctfeedback' => $itemquizdata->correctfeedback]);
            $result->resultstemplate = self::get_itemname('multichoice') . '/multichoiceresults';
        } else {
            $result->hasanswerdetails = false;
        }
        $correctanswers = [];
        $incorrectanswers = [];
        $correctindex = $itemquizdata->correctanswer;

        foreach ($itemquizdata->sentences as $sentance) {
            if ($correctindex == $sentance->indexplusone) {
                $correctanswers[] = $sentance->sentence;
            } else {
                $incorrectanswers[] = $sentance->sentence;
            }
        }

        if (count($correctanswers) == 0) {
            $result->hascorrectanswer = false;
        }
        if (count($incorrectanswers) == 0) {
            $result->hasincorrectanswer = false;
        }

        $result->correctans = ['sentence' => join(' ', $correctanswers)];
        $result->incorrectans = ['sentence' => join('<br> ', $incorrectanswers)];
    }

}
