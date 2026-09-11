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
 * A provider that fails in a chosen way without calling anything, for checking failure handling.
 *
 * Mapping an HTTP status onto an error code is the real driver's job. What this covers is
 * everything downstream of that: the controller stopping cleanly, the envelope carrying the
 * code, and the teacher seeing the intended sentence rather than a raw provider message. Some
 * of those failures - an exhausted quota, a dead network - cannot be produced on demand against
 * a live API, which is the only reason this exists.
 *
 * Diagnostic only. Nothing in the plugin selects it; the CLI harness does, and later the same
 * driver can drive the chat panel through its error states without waiting for a real outage.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class failing_driver implements provider_driver {
    /** @var string One of the result::ERROR_* values. */
    protected string $errorcode;

    /** @var int How many calls have been made, so expiry can be simulated only once. */
    protected int $calls = 0;

    /**
     * Build a driver that always fails in one particular way.
     *
     * @param string $errorcode one of the result::ERROR_* values
     */
    public function __construct(string $errorcode) {
        $this->errorcode = $errorcode;
    }

    /**
     * Always available - the failure is the point, and an unavailable provider would hide it.
     *
     * @return bool
     */
    public function is_available(): bool {
        return true;
    }

    /**
     * This provider's name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'simulated';
    }

    /**
     * Fail in the configured way.
     *
     * @param array $input
     * @param array $tools
     * @param string $instruction
     * @param string|null $previous
     * @return result
     */
    public function create_interaction(
        array $input,
        array $tools,
        string $instruction,
        ?string $previous
    ): result {
        // Expiry is the one failure the controller recovers from rather than reports, so it is
        // simulated on the first call only. Failing the replay too would prove nothing about
        // whether recovery works.
        if ($this->errorcode === result::ERROR_EXPIRED && $this->calls++ > 0) {
            $result = new result();
            $result->status = result::STATUS_COMPLETED;
            $result->interactionid = 'simulated-after-replay';
            $result->steps = [['type' => 'text', 'text' => 'Replayed successfully.']];
            return $result;
        }
        return result::fail($this->errorcode, 'Simulated ' . $this->errorcode . ' failure.');
    }
}
