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

/**
 * Record the teacher's decision on a tool the assistant proposed.
 *
 * The arguments being approved are the ones held in the conversation, not anything the browser
 * sends: an approval that could name its own arguments would be approving one thing and
 * running another. All that travels is the call id and a yes or no.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chatagent_approve extends chatagent_base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'conversationid' => new external_value(PARAM_INT, 'Conversation from chatagent_start'),
            'callid' => new external_value(PARAM_RAW, 'The pending call being decided'),
            'approved' => new external_value(PARAM_BOOL, 'Whether the teacher approved it'),
            'sinceid' => self::sinceid_parameter(),
        ]);
    }

    /**
     * Approve or decline.
     *
     * @param int $conversationid
     * @param string $callid
     * @param bool $approved
     * @param int $sinceid
     * @return array
     */
    public static function execute($conversationid, $callid, $approved, $sinceid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'conversationid' => $conversationid,
            'callid' => $callid,
            'approved' => $approved,
            'sinceid' => $sinceid,
        ]);

        [$conversation] = self::load_own_conversation($params['conversationid']);
        $envelope = self::agent()->approve($conversation, $params['callid'], (bool) $params['approved']);

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
