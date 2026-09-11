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
 * Open the teacher's chat agent conversation about a lesson.
 *
 * Called when the page loads. It makes no model call - it hands back whatever the conversation
 * already holds, so a teacher who navigates away and comes back finds it where they left it,
 * including a tool still waiting for their approval.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chatagent_start extends chatagent_base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the lesson'),
            'sinceid' => self::sinceid_parameter(),
        ]);
    }

    /**
     * Get or start the conversation.
     *
     * @param int $cmid
     * @param int $sinceid
     * @return array
     */
    public static function execute($cmid, $sinceid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'sinceid' => $sinceid]);
        $context = self::require_lesson_access($params['cmid']);
        $cm = get_coursemodule_from_id('minilesson', $params['cmid'], 0, false, MUST_EXIST);

        $conversation = conversation_store::get_or_create($USER->id, $params['cmid'], $cm->course);

        // Report the state the conversation is already in, so the page can redraw an approval
        // card or an unfinished turn rather than looking idle when it is not.
        $status = controller::STATUS_COMPLETE;
        if ($conversation->state === \mod_minilesson\local\chatagent\conversation::STATE_AWAITING_APPROVAL) {
            $status = controller::STATUS_REQUIRES_APPROVAL;
        } else if ($conversation->state === \mod_minilesson\local\chatagent\conversation::STATE_AWAITING_TOOL) {
            $status = controller::STATUS_REQUIRES_TOOL;
        }

        $envelope = [
            'status' => $status,
            'pending' => $conversation->pendingcallid === '' ? null : [
                'callid' => $conversation->pendingcallid,
                'tool' => $conversation->pendingtool,
                'args' => $conversation->pendingargs,
                'summary' => \mod_minilesson\local\chatagent\tools::summarise(
                    $conversation->pendingtool,
                    $conversation->pendingargs
                ),
                'needsapproval' => $status === controller::STATUS_REQUIRES_APPROVAL,
            ],
        ];

        return self::respond($conversation, $envelope, $params['sinceid']);
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
