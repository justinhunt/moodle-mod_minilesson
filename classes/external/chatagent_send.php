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
use mod_minilesson\constants;
use mod_minilesson\local\chatagent\attachments;
use mod_minilesson\local\chatagent\conversation_store;

/**
 * Send the teacher's message and take the first step of the turn.
 *
 * One model call, no tool execution: if the assistant asks for a tool, the browser is told so
 * and calls chatagent_step. Keeping each request to a single call is what stops a turn that
 * needs several tools from outliving a PHP request.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chatagent_send extends chatagent_base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'conversationid' => new external_value(PARAM_INT, 'Conversation from chatagent_start'),
            'text' => new external_value(PARAM_RAW, 'What the teacher typed'),
            'draftitemid' => new external_value(
                PARAM_INT,
                'Draft area behind the composer\'s file manager, or 0 if it has none',
                VALUE_DEFAULT,
                0
            ),
            'sinceid' => self::sinceid_parameter(),
        ]);
    }

    /**
     * Add the message and make the first model call of the turn.
     *
     * @param int $conversationid
     * @param string $text
     * @param int $draftitemid
     * @param int $sinceid
     * @return array
     */
    public static function execute($conversationid, $text, $draftitemid = 0, $sinceid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'conversationid' => $conversationid,
            'text' => $text,
            'draftitemid' => $draftitemid,
            'sinceid' => $sinceid,
        ]);

        [$conversation, $context] = self::load_own_conversation($params['conversationid']);

        if (trim($params['text']) === '' && empty($params['draftitemid'])) {
            throw new \moodle_exception('chatagent_error_emptymessage', constants::M_COMPONENT);
        }
        self::check_rate_limit($USER->id);

        // Bring the conversation's files in line with what the file manager shows - which is
        // also how a file the teacher removed stops being part of the conversation - then send
        // on only the ones the model has not seen.
        attachments::sync_draft($context, $conversation->id, $params['draftitemid']);
        $files = attachments::unsent($conversation, $context);

        $envelope = self::agent()->send_message($conversation, $params['text'], $files);

        return self::respond($conversation, $envelope, $params['sinceid']);
    }

    /**
     * Stop a teacher who is going far beyond ordinary use.
     *
     * The cap is on turns per hour rather than on anything cleverer because the cost being
     * guarded is the site's AI bill, and a turn is the unit a teacher actually spends.
     *
     * @param int $userid
     * @return void
     * @throws \moodle_exception when the teacher has used up the hour's allowance
     */
    protected static function check_rate_limit(int $userid): void {
        $max = (int) get_config(constants::M_COMPONENT, 'chatagentmaxturns');
        if ($max <= 0) {
            $max = 40;
        }
        if (conversation_store::user_turns_since($userid, time() - HOURSECS) >= $max) {
            throw new \moodle_exception('chatagent_error_toomanyturns', constants::M_COMPONENT, '', $max);
        }
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
