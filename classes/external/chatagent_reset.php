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

namespace mod_minilesson\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_minilesson\local\chatagent\controller;
use mod_minilesson\local\chatagent\conversation_store;

/**
 * Throw away the conversation and start a fresh one.
 *
 * The transcript, its attachments and the provider's handle all go: a teacher clearing a
 * conversation means it should be gone, not hidden. The next chatagent_start opens an empty one.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chatagent_reset extends chatagent_base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'conversationid' => new external_value(PARAM_INT, 'Conversation to discard'),
        ]);
    }

    /**
     * Discard the conversation.
     *
     * @param int $conversationid
     * @return array
     */
    public static function execute($conversationid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'conversationid' => $conversationid,
        ]);

        [$conversation] = self::load_own_conversation($params['conversationid']);
        $cmid = $conversation->cmid;
        $courseid = $conversation->courseid;
        conversation_store::delete_where(['id' => $conversation->id]);

        // Hand back an empty conversation rather than nothing, so the page has somewhere to
        // send the teacher's next message without a second round trip.
        $fresh = conversation_store::get_or_create($USER->id, $cmid, $courseid);
        return self::respond($fresh, ['status' => controller::STATUS_COMPLETE], 0);
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return self::envelope_returns();
    }
}
