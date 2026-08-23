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

namespace mod_minilesson\task;

use core\task\scheduled_task;
use mod_minilesson\constants;

/**
 * Hourly housekeeping for the OAuth authorization server: expired authorization
 * codes are useless the moment they expire, and revoked refresh tokens are only
 * kept around briefly to detect replay of an already-rotated token.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth_cleanup extends scheduled_task {
    /** @var int How long a revoked refresh token is kept around for replay detection. */
    const REVOKED_RETENTION = 3 * DAYSECS;

    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('oauthcleanuptask', constants::M_COMPONENT);
    }

    /**
     * Delete expired authorization codes and long-revoked refresh tokens.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $now = time();
        $DB->delete_records_select('minilesson_oauth_codes', 'expires < ?', [$now]);
        // A row is revoked exactly when it is used to mint its successor, so lastused
        // approximates the revocation time closely enough for retention purposes.
        $DB->delete_records_select(
            'minilesson_oauth_refresh',
            '(expires IS NOT NULL AND expires < ?) OR (revoked = 1 AND COALESCE(lastused, timecreated) < ?)',
            [$now, $now - self::REVOKED_RETENTION]
        );
    }
}
