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

/**
 * Callback implementations for Audio chat
 *
 * @package    minilessonitem_audiochat
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_minilesson\constants;
use mod_minilesson\utils;

/**
 * Fetches a student's submission in a audiochat in the current attempt
 * for passing into an audiochat session. This must be done via AJAX because its not available until
 * after the attempt has started.
 * @param array $args
 * @return stdClass|null
 */
function minilessonitem_audiochat_output_fragment_audiochat_fetchstudentsubmission($args) {
    global $DB;
    $args = (object) $args;
    $cm = $DB->get_record('course_modules', ['id' => $args->context->instanceid], '*', MUST_EXIST);
    $minilesson = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
    $itemrecord = $DB->get_record(constants::M_QTABLE, ['id' => $args->itemid]);

    $theaudiochat = utils::fetch_item_from_itemrecord($itemrecord, $minilesson, $args->context);
    if (empty($theaudiochat)) {
        throw new moodle_exception('Item type handler plugin not found');
    }
    $studentsubmission = $theaudiochat->fetch_student_submission();
    return $studentsubmission;
}
