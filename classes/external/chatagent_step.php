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
 * Run the tool the assistant is waiting on, and take the next step of the turn.
 *
 * The browser calls this repeatedly while the status says requires_tool, so each tool
 * execution and the model call that follows it get their own request - and the teacher sees
 * each one land instead of watching a spinner.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chatagent_step extends chatagent_base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'conversationid' => new external_value(PARAM_INT, 'Conversation from chatagent_start'),
            'sinceid' => self::sinceid_parameter(),
        ]);
    }

    /**
     * Take one step.
     *
     * @param int $conversationid
     * @param int $sinceid
     * @return array
     */
    public static function execute($conversationid, $sinceid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'conversationid' => $conversationid,
            'sinceid' => $sinceid,
        ]);

        [$conversation] = self::load_own_conversation($params['conversationid']);
        $envelope = self::agent()->step($conversation);

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
