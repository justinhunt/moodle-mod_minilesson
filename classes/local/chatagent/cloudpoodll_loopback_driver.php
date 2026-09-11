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

namespace mod_minilesson\local\chatagent;

/**
 * The Cloud Poodll driver with the network hop replaced by an in-process call.
 *
 * On a development site that also has local_cpapi installed, this runs the Cloud Poodll web
 * service in the same process, as a named Cloud Poodll account, so the whole brokered path - the
 * licence check, the daily allowance, tenant binding, the real call to Google, and this driver's
 * handling of every envelope - can be tested before the endpoint is deployed. Only the HTTP hop
 * and the token are skipped.
 *
 * Diagnostic only. Nothing in the plugin selects it; the CLI harness does.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cloudpoodll_loopback_driver extends cloudpoodll_driver {
    /** @var \stdClass The Cloud Poodll account the call is made as. */
    protected \stdClass $cpuser;

    /**
     * Build the driver for one Cloud Poodll account on this site.
     *
     * @param string $cpusername the account's username
     */
    public function __construct(string $cpusername) {
        global $DB;
        parent::__construct();
        $this->cpuser = $DB->get_record('user', ['username' => $cpusername], '*', MUST_EXIST);
    }

    /**
     * Always available: the account stands in for the credentials.
     *
     * @return bool
     */
    public function is_available(): bool {
        return true;
    }

    /**
     * No token is needed in-process.
     *
     * @param bool $force
     * @return string
     */
    protected function token(bool $force) {
        return 'loopback';
    }

    /**
     * Run the web service as the Cloud Poodll account and return what the REST server would.
     *
     * The current user is swapped for the call and put back afterwards, whatever happens: the
     * tools that run next must run as the teacher, not as the Cloud Poodll account.
     *
     * @param string $token
     * @param array $params
     * @return array [http code, response body]
     */
    protected function send(string $token, array $params): array {
        global $USER;
        $teacher = clone($USER);
        \core\session\manager::set_user($this->cpuser);
        try {
            $response = \core_external\external_api::call_external_function(self::WSFUNCTION, $params);
        } finally {
            \core\session\manager::set_user($teacher);
        }
        if (!empty($response['error'])) {
            // The REST server's shape for an exception.
            $exception = (array) $response['exception'];
            return [200, json_encode([
                'exception' => 'loopback',
                'errorcode' => $exception['errorcode'] ?? '',
                'message' => $exception['message'] ?? '',
            ])];
        }
        return [200, json_encode($response['data'], JSON_UNESCAPED_UNICODE)];
    }
}
