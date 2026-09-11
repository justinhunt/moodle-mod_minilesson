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
 * The real Gemini driver, deliberately misconfigured, to exercise failures against the live API.
 *
 * Where failing_driver short-circuits the call entirely, this one really does send a request -
 * so it checks what the driver makes of a genuine rejection or a genuine network failure, which
 * is the part a stub cannot vouch for.
 *
 * It works by subclassing rather than by changing the site's settings: a diagnostic that leaves
 * a broken API key behind when it dies half way through is worse than no diagnostic.
 *
 * Diagnostic only. Nothing in the plugin selects it; the CLI harness does.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class misconfigured_gemini_driver extends gemini_driver {
    /** @var string Send a key the API will reject. */
    const MODE_BADKEY = 'badkey';

    /** @var string Send the request to a host that does not resolve. */
    const MODE_BADHOST = 'badhost';

    /** @var string A host no DNS will answer for; .invalid is reserved for exactly this. */
    const UNREACHABLE = 'https://interactions.invalid/v1beta/interactions';

    /** @var string Which fault to introduce. */
    protected string $mode;

    /**
     * Build the driver with one fault introduced.
     *
     * @param string $mode one of the MODE_* constants
     */
    public function __construct(string $mode) {
        parent::__construct();
        $this->mode = $mode;
        if ($mode === self::MODE_BADKEY) {
            $this->apikey = 'not-a-real-key';
        }
    }

    /**
     * The real endpoint, unless we are testing what happens when it cannot be reached.
     *
     * @return string
     */
    protected function endpoint(): string {
        return $this->mode === self::MODE_BADHOST ? self::UNREACHABLE : parent::endpoint();
    }
}
